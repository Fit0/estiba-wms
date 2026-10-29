import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import type { Role } from '../auth/roles';
import { json, problem } from '../testing/fakeApi';
import { asn, product, receipt } from '../testing/inboundFixtures';
import { apiOf, open, withSession } from '../testing/renderApp';

const counted = (units: number, condition = 'good') => ({
  lineNumber: 1,
  productId: 'p-1',
  units,
  lot: null,
  expiryDate: null,
  condition,
  receivedAt: '2026-10-28T08:20:00+00:00',
});

function api(roles: Role[], current: () => unknown, extra = {}) {
  return apiOf(roles, {
    ...withSession(roles),
    'GET /api/warehouses': () => json([]),
    'GET /api/asns/a-1': () => json(asn()),
    'GET /api/products/p-1': () => json(product),
    'GET /api/receipts/r-1': () => json(current()),
    ...extra,
  });
}

async function count(user: ReturnType<typeof userEvent.setup>, gtin: string, units: string) {
  await user.type(await screen.findByLabelText('Código de barras (GTIN)'), gtin);
  await user.type(screen.getByLabelText('Unidades'), units);
  await user.click(screen.getByRole('button', { name: 'Contar' }));
}

describe('a receipt at the dock', () => {
  it('shows what was expected against what was counted', async () => {
    open(
      '/receipts/r-1',
      api(['operator'], () =>
        receipt({
          lines: [counted(200)],
          discrepancies: [{ type: 'shortage', productId: 'p-1', units: 40 }],
        }),
      ),
    );

    expect(
      await screen.findByRole('heading', { name: 'Recepción de ASN-2026-0042' }),
    ).toBeInTheDocument();
    const expected = await screen.findByRole('region', { name: 'Esperado y contado' });
    expect(await within(expected).findByText('CAM-LIN · Camisa de lino')).toBeInTheDocument();
    expect(within(expected).getByText('240')).toBeInTheDocument();
    expect(within(expected).getByText('200')).toBeInTheDocument();
    expect(within(expected).getByText('-40')).toBeInTheDocument();

    const discrepancies = screen.getByRole('region', { name: 'Discrepancias' });
    expect(within(discrepancies).getByText('Faltan')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Volver a la entrega' })).toHaveAttribute(
      'href',
      '/inbound/a-1',
    );
  });

  it('counts an item, sends what was typed and clears the form for the next one', async () => {
    const user = userEvent.setup();
    const sent: unknown[] = [];
    let current = receipt();
    open(
      '/receipts/r-1',
      api(['operator'], () => current, {
        'POST /api/receipts/r-1/items': ({ body }: { body: unknown }) => {
          sent.push(body);
          current = receipt({
            lines: [counted(12)],
            discrepancies: [{ type: 'shortage', productId: 'p-1', units: 228 }],
          });

          return json(current);
        },
      }),
    );

    await count(user, '8412345678905', '12');

    const lines = await screen.findByRole('region', { name: 'Conteos' });
    expect(await within(lines).findByText('Bueno')).toBeInTheDocument();
    expect(sent).toEqual([
      { gtin: '8412345678905', units: 12, lot: null, expiryDate: null, condition: 'good' },
    ]);
    await waitFor(() => {
      expect(screen.getByLabelText('Código de barras (GTIN)')).toHaveValue('');
    });
    await waitFor(() => {
      expect(screen.getByLabelText('Código de barras (GTIN)')).toHaveFocus();
    });
    expect(
      within(screen.getByRole('region', { name: 'Discrepancias' })).getByText('Faltan'),
    ).toBeInTheDocument();
  });

  it('checks the form before it asks the API', async () => {
    const user = userEvent.setup();
    const requests = api(['operator'], () => receipt());
    open('/receipts/r-1', requests);

    await user.click(await screen.findByRole('button', { name: 'Contar' }));

    expect(await screen.findByText('Escribe un código de 8 a 14 cifras.')).toBeInTheDocument();
    expect(requests.calls.some((call) => call.startsWith('POST /api/receipts'))).toBe(false);
  });

  it('shows why the API refuses a count, and keeps what was typed', async () => {
    const user = userEvent.setup();
    open(
      '/receipts/r-1',
      api(['operator'], () => receipt(), {
        'POST /api/receipts/r-1/items': () => problem(404, 'catalog.product_not_found'),
      }),
    );

    await count(user, '8412345678905', '5');

    expect(await screen.findByRole('alert')).toHaveTextContent('No se ha podido guardar');
    expect(screen.getByLabelText('Unidades')).toHaveValue('5');
  });

  it('keeps an operator from closing a receipt with an excess nobody approved', async () => {
    open(
      '/receipts/r-1',
      api(['operator'], () =>
        receipt({
          overReceivedUnits: 10,
          lines: [counted(250)],
          discrepancies: [{ type: 'excess', productId: 'p-1', units: 10 }],
        }),
      ),
    );

    expect(await screen.findByRole('button', { name: 'Cerrar la recepción' })).toBeDisabled();
    expect(screen.getAllByText(/Hay 10 unidades más de las anunciadas/).length).toBeGreaterThan(0);
    expect(screen.queryByRole('button', { name: 'Aprobar el exceso' })).not.toBeInTheDocument();
  });

  it('lets a supervisor approve the excess, and then close', async () => {
    const user = userEvent.setup();
    let current = receipt({ overReceivedUnits: 10, lines: [counted(250)] });
    const requests = api(['supervisor'], () => current, {
      'POST /api/receipts/r-1/approve-over-receipt': () => {
        current = receipt({
          overReceivedUnits: 10,
          overReceiptApproved: true,
          lines: [counted(250)],
        });

        return json(current);
      },
      'POST /api/receipts/r-1/close': () => {
        current = receipt({
          status: 'closed',
          closedAt: '2026-10-28T09:00:00+00:00',
          overReceivedUnits: 10,
          overReceiptApproved: true,
          lines: [counted(250)],
        });

        return json(current);
      },
    });
    open('/receipts/r-1', requests);

    await user.click(await screen.findByRole('button', { name: 'Aprobar el exceso' }));
    expect(await screen.findByText('El exceso está aprobado.')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Cerrar la recepción' }));
    const dialog = await screen.findByRole('dialog', {
      name: 'Cerrar la recepción de ASN-2026-0042',
    });
    expect(requests.calls).not.toContain('POST /api/receipts/r-1/close');
    await user.click(within(dialog).getByRole('button', { name: 'Cerrar recepción' }));

    await waitFor(() => {
      expect(screen.queryByRole('button', { name: 'Contar' })).not.toBeInTheDocument();
    });
    expect(requests.calls).toContain('POST /api/receipts/r-1/close');
    expect(screen.getByText('Dando de alta el stock…')).toBeInTheDocument();
  });

  it('shows a closed receipt without a way to count', async () => {
    open(
      '/receipts/r-1',
      api(['operator'], () =>
        receipt({
          status: 'closed',
          closedAt: '2026-10-28T09:00:00+00:00',
          stockBooked: true,
          lines: [counted(240)],
        }),
      ),
    );

    expect(await screen.findByText('Stock dado de alta')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Contar' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Cerrar la recepción' })).not.toBeInTheDocument();
  });

  it('says so when the receipt does not exist', async () => {
    open(
      '/receipts/r-9',
      apiOf(['operator'], {
        ...withSession(['operator']),
        'GET /api/receipts/r-9': () => problem(404, 'inbound.receipt_not_found'),
      }),
    );

    expect(
      await screen.findByRole('heading', { name: 'No se ha encontrado la recepción.' }),
    ).toBeInTheDocument();
  });
});
