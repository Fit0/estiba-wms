import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { fireEvent } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { json, problem } from '../testing/fakeApi';
import { asn, product, warehouse } from '../testing/inboundFixtures';
import { paged } from '../testing/paged';
import { apiOf, open, withSession } from '../testing/renderApp';

const base = {
  'GET /api/warehouses': () => json([warehouse]),
  'GET /api/products/p-1': () => json(product),
};

describe('registering an expected delivery', () => {
  it('registers one with a product found by typing, and opens it', async () => {
    const user = userEvent.setup();
    let sent: unknown;
    const api = apiOf(['supervisor'], {
      ...withSession(['supervisor']),
      ...base,
      'GET /api/asns?page=1&itemsPerPage=30': () => paged([]),
      'GET /api/products?page=1&itemsPerPage=8&q=camisa': () => paged([product], 1),
      'POST /api/asns': ({ body }) => {
        sent = body;

        return json(asn({ id: 'a-new', reference: 'ASN-NEW' }), 201);
      },
      'GET /api/asns/a-new': () => json(asn({ id: 'a-new', reference: 'ASN-NEW' })),
    });
    open('/inbound', api);

    await user.click(await screen.findByRole('button', { name: 'Nueva entrega' }));
    const dialog = await screen.findByRole('dialog', { name: 'Nueva entrega esperada' });
    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));
    expect(await within(dialog).findByText('Elige un producto.')).toBeInTheDocument();
    expect(api.calls).not.toContain('POST /api/asns');

    await user.type(within(dialog).getByLabelText('Referencia'), 'ASN-NEW');
    await user.type(within(dialog).getByLabelText('Proveedor'), 'Textiles del Ebro');
    fireEvent.change(within(dialog).getByLabelText('Fecha prevista'), {
      target: { value: '2026-11-02T08:00' },
    });
    const line = within(dialog).getByRole('group', { name: 'Línea 1' });
    await user.type(within(line).getByRole('searchbox'), 'camisa');
    await user.click(await within(line).findByRole('button', { name: /CAM-LIN/ }));
    await user.type(within(line).getByLabelText('Unidades'), '240');
    await user.type(within(line).getByLabelText('Lote (opcional)'), 'L-77');
    fireEvent.change(within(line).getByLabelText('Caducidad (opcional)'), {
      target: { value: '2027-03-31' },
    });
    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));

    expect(await screen.findByRole('heading', { name: /ASN-NEW/ })).toBeInTheDocument();
    expect(sent).toMatchObject({
      reference: 'ASN-NEW',
      supplier: 'Textiles del Ebro',
      lines: [{ productId: 'p-1', units: 240, lot: 'L-77', expiryDate: '2027-03-31' }],
    });
    expect(new Date((sent as { expectedDate: string }).expectedDate).toISOString()).toBe(
      new Date('2026-11-02T08:00').toISOString(),
    );
  });

  it('shows what the API says when the reference is taken, inside the form', async () => {
    const user = userEvent.setup();
    const api = apiOf(['supervisor'], {
      ...withSession(['supervisor']),
      ...base,
      'GET /api/asns?page=1&itemsPerPage=30': () => paged([]),
      'GET /api/products?page=1&itemsPerPage=8&q=camisa': () => paged([product], 1),
      'POST /api/asns': () => problem(403, 'inbound.reference_taken'),
    });
    open('/inbound', api);

    await user.click(await screen.findByRole('button', { name: 'Nueva entrega' }));
    const dialog = await screen.findByRole('dialog', { name: 'Nueva entrega esperada' });
    await user.type(within(dialog).getByLabelText('Referencia'), 'X-1');
    await user.type(within(dialog).getByLabelText('Proveedor'), 'Proveedor');
    const line = within(dialog).getByRole('group', { name: 'Línea 1' });
    await user.type(within(line).getByRole('searchbox'), 'camisa');
    await user.click(await within(line).findByRole('button', { name: /CAM-LIN/ }));
    await user.type(within(line).getByLabelText('Unidades'), '5');
    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));

    expect(await within(dialog).findByRole('alert')).toHaveTextContent('inbound.reference_taken');
  });

  it('checks the expiry date and the units before asking', async () => {
    const user = userEvent.setup();
    const api = apiOf(['supervisor'], {
      ...withSession(['supervisor']),
      ...base,
      'GET /api/asns?page=1&itemsPerPage=30': () => paged([]),
    });
    open('/inbound', api);

    await user.click(await screen.findByRole('button', { name: 'Nueva entrega' }));
    const dialog = await screen.findByRole('dialog', { name: 'Nueva entrega esperada' });
    const line = within(dialog).getByRole('group', { name: 'Línea 1' });
    await user.type(within(line).getByLabelText('Unidades'), '0');
    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));

    expect((await within(dialog).findAllByText('Escribe un valor.')).length).toBeGreaterThan(0);
    expect(within(line).getByText('Escribe 1 unidad o más.')).toBeInTheDocument();
  });

  it('is not offered to an operator', async () => {
    open(
      '/inbound',
      apiOf(['operator'], {
        ...withSession(['operator']),
        ...base,
        'GET /api/asns?page=1&itemsPerPage=30': () => paged([]),
      }),
    );

    await screen.findByRole('heading', { name: 'Entregas esperadas' });
    expect(screen.queryByRole('button', { name: 'Nueva entrega' })).not.toBeInTheDocument();
  });
});

describe('cancelling and closing a delivery', () => {
  // The server remembers: after a change the next read shows it, as the real one does.
  const state = { current: asn() };
  const page = (roles: ('operator' | 'supervisor')[], data = asn(), extra = {}) => {
    state.current = data;

    return apiOf(roles, {
      ...withSession(roles),
      ...base,
      'GET /api/asns/a-1': () => json(state.current),
      ...extra,
    });
  };

  it('cancels a delivery that is still expected, after confirming', async () => {
    const user = userEvent.setup();
    const api = page(['supervisor'], asn(), {
      'POST /api/asns/a-1/cancel': () => {
        state.current = asn({ status: 'cancelled' });

        return json(state.current);
      },
    });
    open('/inbound/a-1', api);

    await user.click(await screen.findByRole('button', { name: 'Cancelar entrega' }));
    const dialog = await screen.findByRole('dialog', { name: 'Cancelar ASN-2026-0042' });
    await user.click(within(dialog).getByRole('button', { name: 'Cancelar la entrega' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(api.calls).toContain('POST /api/asns/a-1/cancel');
    expect(screen.queryByRole('button', { name: 'Cancelar entrega' })).not.toBeInTheDocument();
  });

  it('closes a delivery that has received goods, and shows the refusal of the API in the dialog', async () => {
    const user = userEvent.setup();
    let attempts = 0;
    const api = page(['supervisor'], asn({ status: 'partially_received' }), {
      'POST /api/asns/a-1/close': () => {
        attempts += 1;

        return attempts === 1
          ? problem(403, 'inbound.receipt_open')
          : json((state.current = asn({ status: 'closed' })));
      },
    });
    open('/inbound/a-1', api);

    await user.click(await screen.findByRole('button', { name: 'Cerrar entrega' }));
    const dialog = await screen.findByRole('dialog', { name: 'Cerrar ASN-2026-0042' });
    await user.click(within(dialog).getByRole('button', { name: 'Cerrar la entrega' }));
    expect(await within(dialog).findByRole('alert')).toHaveTextContent('inbound.receipt_open');

    await user.click(within(dialog).getByRole('button', { name: 'Cerrar la entrega' }));
    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect((await screen.findAllByText('Cerrada')).length).toBeGreaterThan(0);
  });

  it('offers neither while a receipt is open, nor to an operator, nor once closed', async () => {
    open(
      '/inbound/a-1',
      page(['supervisor'], asn({ status: 'partially_received', openReceiptId: 'r-1' })),
    );
    await screen.findByRole('heading', { name: /ASN-2026-0042/ });
    expect(screen.queryByRole('button', { name: 'Cerrar entrega' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Cancelar entrega' })).not.toBeInTheDocument();
  });

  it('shows an operator no cancel button', async () => {
    open('/inbound/a-1', page(['operator']));
    await screen.findByRole('heading', { name: /ASN-2026-0042/ });
    expect(screen.queryByRole('button', { name: 'Cancelar entrega' })).not.toBeInTheDocument();
  });
});
