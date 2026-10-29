import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { json, problem } from '../testing/fakeApi';
import { asn, location, product, receipt, warehouse } from '../testing/inboundFixtures';
import { paged } from '../testing/paged';
import { apiOf, open, withSession } from '../testing/renderApp';

const docks = 'GET /api/warehouses/w-1/locations?page=1&itemsPerPage=100&status=active';

function api(extra = {}, data = asn()) {
  return apiOf(['operator'], {
    ...withSession(['operator']),
    'GET /api/warehouses': () => json([warehouse]),
    'GET /api/asns/a-1': () => json(data),
    'GET /api/products/p-1': () => json(product),
    ...extra,
  });
}

describe('one expected delivery', () => {
  it('shows its lines with the product, what was announced and what has arrived', async () => {
    open(
      '/inbound/a-1',
      api(
        {},
        asn({
          lines: [
            {
              productId: 'p-1',
              expectedUnits: 240,
              receivedUnits: 100,
              lot: 'L1',
              expiryDate: '2027-01-31',
            },
          ],
        }),
      ),
    );

    expect(
      await screen.findByRole('heading', { name: 'ASN-2026-0042 · Textiles del Ebro' }),
    ).toBeInTheDocument();
    const lines = await screen.findByRole('region', { name: 'Líneas de la entrega' });
    expect(await within(lines).findByText('CAM-LIN · Camisa de lino')).toBeInTheDocument();
    expect(within(lines).getByText('240')).toBeInTheDocument();
    expect(within(lines).getByText('140')).toBeInTheDocument();
    expect(within(lines).getByText('L1')).toBeInTheDocument();
  });

  it('opens a receipt at a dock of the active warehouse and goes to it', async () => {
    const user = userEvent.setup();
    const sent: unknown[] = [];
    const requests = api({
      [docks]: () => paged([location('A-01-01', 'reserve'), location('D-01', 'dock')]),
      'POST /api/asns/a-1/receipts': ({ body }: { body: unknown }) => {
        sent.push(body);

        return json(receipt(), 201);
      },
      'GET /api/receipts/r-1': () => json(receipt()),
    });
    open('/inbound/a-1', requests);

    await user.click(await screen.findByRole('button', { name: 'Abrir recepción' }));
    const dialog = await screen.findByRole('dialog', { name: 'Abrir recepción de ASN-2026-0042' });
    expect(await within(dialog).findByRole('combobox', { name: 'Muelle' })).toHaveTextContent(
      'D-01',
    );
    await user.click(within(dialog).getByRole('button', { name: 'Abrir recepción' }));

    expect(
      await screen.findByRole('heading', { name: 'Recepción de ASN-2026-0042' }),
    ).toBeInTheDocument();
    expect(sent).toEqual([{ dockLocationId: 'l-D-01' }]);
  });

  it('says there is no dock when the warehouse has none', async () => {
    const user = userEvent.setup();
    open('/inbound/a-1', api({ [docks]: () => paged([location('A-01-01', 'reserve')]) }));

    await user.click(await screen.findByRole('button', { name: 'Abrir recepción' }));

    expect(await screen.findByText(/no tiene muelles activos/)).toBeInTheDocument();
  });

  it('shows the API reason when it refuses to open the receipt', async () => {
    const user = userEvent.setup();
    open(
      '/inbound/a-1',
      api({
        [docks]: () => paged([location('D-01', 'dock')]),
        'POST /api/asns/a-1/receipts': () => problem(409, 'inbound.asn_receipt_already_open'),
      }),
    );

    await user.click(await screen.findByRole('button', { name: 'Abrir recepción' }));
    const dialog = await screen.findByRole('dialog');
    await within(dialog).findByRole('combobox', { name: 'Muelle' });
    await user.click(within(dialog).getByRole('button', { name: 'Abrir recepción' }));

    expect(await within(dialog).findByRole('alert')).toHaveTextContent('No se ha podido guardar');
  });

  it('continues the receipt that is open, and offers none for a closed delivery', async () => {
    open('/inbound/a-1', api({}, asn({ status: 'partially_received', openReceiptId: 'r-1' })));

    expect(await screen.findByRole('link', { name: 'Continuar la recepción' })).toHaveAttribute(
      'href',
      '/receipts/r-1',
    );
    expect(screen.queryByRole('button', { name: 'Abrir recepción' })).not.toBeInTheDocument();
  });

  it('offers no receipt for a delivery that is closed', async () => {
    open('/inbound/a-1', api({}, asn({ status: 'closed' })));

    expect(await screen.findByText('Esta entrega ya no admite recepciones.')).toBeInTheDocument();
    await waitFor(() => {
      expect(screen.queryByRole('button', { name: 'Abrir recepción' })).not.toBeInTheDocument();
    });
  });

  it('says so when the delivery does not exist', async () => {
    open(
      '/inbound/a-9',
      apiOf(['operator'], {
        ...withSession(['operator']),
        'GET /api/asns/a-9': () => problem(404, 'inbound.asn_not_found'),
      }),
    );

    expect(
      await screen.findByRole('heading', { name: 'No se ha encontrado la entrega.' }),
    ).toBeInTheDocument();
  });
});
