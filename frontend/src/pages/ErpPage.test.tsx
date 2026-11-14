import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { json, problem } from '../testing/fakeApi';
import { paged } from '../testing/paged';
import { apiOf, open, withSession } from '../testing/renderApp';

const confirmation = (over: Record<string, unknown> = {}) => ({
  shipmentId: 's-1',
  orderReference: 'PED-0001',
  status: 'sent',
  attempts: 1,
  carrier: 'SEUR',
  trackingNumber: 'TRK-1',
  parcels: 1,
  lines: [{ sku: 'SKU-1', units: 2 }],
  shippedAt: '2026-11-10T10:00:00+00:00',
  nextAttemptAt: '2026-11-10T10:00:00+00:00',
  lastError: null,
  createdAt: '2026-11-10T10:00:00+00:00',
  sentAt: '2026-11-10T10:01:00+00:00',
  ...over,
});
const imported = (over: Record<string, unknown> = {}) => ({
  id: 'i-1',
  fileName: 'orders-1.csv',
  result: 'processed',
  orders: 3,
  errorCode: null,
  message: null,
  recordedAt: '2026-11-10T09:00:00+00:00',
  ...over,
});
const failed = confirmation({
  shipmentId: 's-2',
  orderReference: 'PED-0002',
  status: 'failed',
  attempts: 5,
  lastError: 'The ERP is not available',
  sentAt: null,
});

const confirmationsUrl = 'GET /api/erp/confirmations?page=1&itemsPerPage=10';
const importsUrl = 'GET /api/erp/imports?page=1&itemsPerPage=10';

describe('the integration with the ERP', () => {
  it('shows what goes out and what comes in', async () => {
    open(
      '/erp',
      apiOf(['supervisor'], {
        ...withSession(['supervisor']),
        [confirmationsUrl]: () => paged([confirmation(), failed]),
        [importsUrl]: () =>
          paged([
            imported(),
            imported({
              id: 'i-2',
              fileName: 'broken.csv',
              result: 'rejected',
              orders: 0,
              message: 'Unknown SKU',
            }),
          ]),
      }),
    );

    expect(await screen.findByRole('heading', { name: 'Integración con el ERP' })).toBeVisible();
    expect(await screen.findByText('PED-0001')).toBeInTheDocument();
    expect(screen.getAllByText('SEUR · TRK-1').length).toBeGreaterThan(0);
    expect(screen.getAllByText('The ERP is not available').length).toBeGreaterThan(0);
    expect(await screen.findByText('orders-1.csv')).toBeInTheDocument();
    expect(screen.getAllByText('Unknown SKU').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Rechazado').length).toBeGreaterThan(0);
  });

  it('offers to retry only the failed confirmations, and asks the API', async () => {
    const user = userEvent.setup();
    const api = apiOf(['supervisor'], {
      ...withSession(['supervisor']),
      [confirmationsUrl]: () => paged([confirmation(), failed]),
      [importsUrl]: () => paged([]),
      'POST /api/erp/confirmations/s-2/retry': () => json({ ...failed, status: 'pending' }),
    });
    open('/erp', api);

    await screen.findByText('PED-0002');
    expect(screen.getAllByRole('button', { name: /Reintentar/ })).toHaveLength(1);
    await user.click(
      screen.getByRole('button', { name: 'Reintentar la confirmación del pedido PED-0002' }),
    );

    expect(await screen.findByText('La confirmación vuelve a la cola.')).toBeInTheDocument();
    expect(api.calls).toContain('POST /api/erp/confirmations/s-2/retry');
  });

  it('says so when the retry is refused', async () => {
    const user = userEvent.setup();
    open(
      '/erp',
      apiOf(['supervisor'], {
        ...withSession(['supervisor']),
        [confirmationsUrl]: () => paged([failed]),
        [importsUrl]: () => paged([]),
        'POST /api/erp/confirmations/s-2/retry': () =>
          problem(404, 'erp.failed_confirmation_not_found'),
      }),
    );

    await user.click(
      await screen.findByRole('button', { name: 'Reintentar la confirmación del pedido PED-0002' }),
    );

    expect(await screen.findByText(/No se pudo reintentar la confirmación/)).toBeInTheDocument();
  });

  it('asks for the status and the result the person filters by', async () => {
    const user = userEvent.setup();
    const { router } = open(
      '/erp',
      apiOf(['supervisor'], {
        ...withSession(['supervisor']),
        [confirmationsUrl]: () => paged([confirmation()]),
        [importsUrl]: () => paged([imported()]),
        'GET /api/erp/confirmations?page=1&itemsPerPage=10&status=failed': () => paged([]),
        'GET /api/erp/imports?page=1&itemsPerPage=10&result=rejected': () => paged([]),
      }),
    );

    await screen.findByText('PED-0001');
    await user.click(screen.getByRole('combobox', { name: 'Estado' }));
    await user.click(await screen.findByRole('option', { name: 'Fallida' }));
    expect(await screen.findByText('No hay confirmaciones con este estado.')).toBeInTheDocument();
    await user.click(screen.getByRole('combobox', { name: 'Resultado' }));
    await user.click(await screen.findByRole('option', { name: 'Rechazado' }));
    expect(await screen.findByText('No hay ficheros con este resultado.')).toBeInTheDocument();
    expect(router.state.location.search).toBe('?status=failed&result=rejected');
  });

  it('keeps an operator out', async () => {
    open('/erp', apiOf(['operator'], withSession(['operator'])));

    expect(await screen.findByRole('heading', { name: 'Sin permiso' })).toBeInTheDocument();
  });
});
