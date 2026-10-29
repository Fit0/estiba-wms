import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { json, problem } from '../testing/fakeApi';
import { warehouse } from '../testing/inboundFixtures';
import { paged } from '../testing/paged';
import { apiOf, open, withSession } from '../testing/renderApp';

const wave = (overrides: Record<string, unknown> = {}) => ({
  id: 'wave-0001-aaaa',
  warehouseId: 'w-1',
  status: 'planned',
  orderIds: ['o-1', 'o-2'],
  pickListIds: [],
  createdAt: '2026-10-29T09:00:00+00:00',
  releasedAt: null,
  completedAt: null,
  ...overrides,
});

const order = (id: string, reference: string, overrides: Record<string, unknown> = {}) => ({
  id,
  reference,
  warehouseId: 'w-1',
  customer: `Cliente ${reference}`,
  shipTo: { street: 'Calle 1', postalCode: '50001', city: 'Zaragoza', countryCode: 'ES' },
  priority: 'normal',
  cutOffAt: '2026-10-30T14:00:00+00:00',
  registeredAt: '2026-10-29T08:00:00+00:00',
  status: 'allocated',
  shortageUnits: 0,
  backorderAccepted: false,
  waveId: null,
  lines: [],
  ...overrides,
});

const pickList = (id: string, overrides: Record<string, unknown> = {}) => ({
  id,
  waveId: 'wave-0001-aaaa',
  status: 'in_progress',
  operatorId: null,
  unitsToPick: 100,
  unitsPicked: 40,
  lines: [],
  createdAt: '2026-10-29T09:10:00+00:00',
  startedAt: null,
  completedAt: null,
  ...overrides,
});

describe('the waves list', () => {
  it('lists the waves with their orders, pick lists and status', async () => {
    open(
      '/waves',
      apiOf(['operator'], {
        ...withSession(['operator']),
        'GET /api/warehouses': () => json([warehouse]),
        'GET /api/waves?page=1&itemsPerPage=30': () =>
          paged(
            [wave(), wave({ id: 'zzzz0002-bbbb', status: 'completed', pickListIds: ['a', 'b'] })],
            2,
          ),
      }),
    );

    const table = await screen.findByRole('region', { name: 'Olas' });
    expect(await within(table).findByText('wave-000')).toBeInTheDocument();
    expect(within(table).getByText('zzzz0002')).toBeInTheDocument();
    expect(within(table).getByText('Planificada')).toBeInTheDocument();
    expect(within(table).getByText('Completada')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Nueva ola' })).not.toBeInTheDocument();
  });

  it('creates a wave from the ready orders of the active warehouse', async () => {
    const user = userEvent.setup();
    let sent: unknown;
    const api = apiOf(['supervisor'], {
      ...withSession(['supervisor']),
      'GET /api/warehouses': () => json([warehouse]),
      'GET /api/waves?page=1&itemsPerPage=30': () => paged([]),
      'GET /api/orders?page=1&itemsPerPage=100&status=allocated': () =>
        paged([order('o-1', 'PED-1'), order('o-3', 'PED-3', { waveId: 'x' })]),
      'GET /api/orders?page=1&itemsPerPage=100&status=partially_allocated': () =>
        paged([
          order('o-2', 'PED-2', { status: 'partially_allocated', backorderAccepted: true }),
          order('o-4', 'PED-4', { status: 'partially_allocated' }),
        ]),
      'POST /api/waves': ({ body }) => {
        sent = body;

        return json(wave({ id: 'wave-new-1234' }), 201);
      },
      'GET /api/waves/wave-new-1234': () => json(wave({ id: 'wave-new-1234' })),
      'GET /api/pick-lists?waveId=wave-new-1234': () => json([]),
      'GET /api/orders/o-1': () => json(order('o-1', 'PED-1')),
      'GET /api/orders/o-2': () => json(order('o-2', 'PED-2')),
    });
    open('/waves', api);

    await user.click(await screen.findByRole('button', { name: 'Nueva ola' }));
    const dialog = await screen.findByRole('dialog', { name: 'Nueva ola' });
    expect(await within(dialog).findByRole('checkbox', { name: /PED-1/ })).toBeInTheDocument();
    expect(within(dialog).getByRole('checkbox', { name: /PED-2/ })).toBeInTheDocument();
    expect(within(dialog).queryByRole('checkbox', { name: /PED-3/ })).not.toBeInTheDocument();
    expect(within(dialog).queryByRole('checkbox', { name: /PED-4/ })).not.toBeInTheDocument();

    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));
    expect(await within(dialog).findByText('Marca al menos un pedido.')).toBeInTheDocument();
    expect(api.calls).not.toContain('POST /api/waves');

    await user.click(within(dialog).getByRole('checkbox', { name: /PED-1/ }));
    await user.click(within(dialog).getByRole('checkbox', { name: /PED-2/ }));
    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));

    expect(await screen.findByRole('heading', { name: 'Ola wave-new' })).toBeInTheDocument();
    expect(sent).toEqual({ orderIds: ['o-1', 'o-2'] });
  });

  it('says so when no order is ready', async () => {
    const user = userEvent.setup();
    open(
      '/waves',
      apiOf(['supervisor'], {
        ...withSession(['supervisor']),
        'GET /api/warehouses': () => json([warehouse]),
        'GET /api/waves?page=1&itemsPerPage=30': () => paged([]),
        'GET /api/orders?page=1&itemsPerPage=100&status=allocated': () => paged([]),
        'GET /api/orders?page=1&itemsPerPage=100&status=partially_allocated': () => paged([]),
      }),
    );

    await user.click(await screen.findByRole('button', { name: 'Nueva ola' }));
    expect(await screen.findByText(/No hay pedidos listos/)).toBeInTheDocument();
  });
});

describe('one wave', () => {
  const base = (roles: ('operator' | 'supervisor')[], current = wave(), extra = {}) =>
    apiOf(roles, {
      ...withSession(roles),
      'GET /api/waves/wave-0001-aaaa': () => json(current),
      'GET /api/pick-lists?waveId=wave-0001-aaaa': () => json([]),
      'GET /api/orders/o-1': () => json(order('o-1', 'PED-1', { status: 'in_wave' })),
      'GET /api/orders/o-2': () => json(order('o-2', 'PED-2', { status: 'in_wave' })),
      ...extra,
    });

  it('shows its orders and the progress of its pick lists', async () => {
    open(
      '/waves/wave-0001-aaaa',
      base(['operator'], wave({ status: 'released', pickListIds: ['pl-1'] }), {
        'GET /api/pick-lists?waveId=wave-0001-aaaa': () =>
          json([
            pickList('pl-0001-xxxx'),
            pickList('pl-0002-yyyy', { status: 'completed', unitsPicked: 100 }),
          ]),
      }),
    );

    const orders = await screen.findByRole('region', { name: 'Pedidos incluidos' });
    expect(await within(orders).findByText('PED-1')).toBeInTheDocument();
    expect(within(orders).getByText('Cliente PED-2')).toBeInTheDocument();
    const lists = await screen.findByRole('region', { name: 'Listas de picking' });
    expect(await within(lists).findByText('40 de 100')).toBeInTheDocument();
    expect(within(lists).getByText('100 de 100')).toBeInTheDocument();
    expect(within(lists).getAllByText('Sin asignar')).toHaveLength(2);
    expect(screen.queryByRole('button', { name: 'Lanzar al almacén' })).not.toBeInTheDocument();
  });

  it('lets a supervisor send a planned wave to the floor in two lists', async () => {
    const user = userEvent.setup();
    let sent: unknown;
    const api = base(['supervisor'], wave(), {
      'POST /api/waves/wave-0001-aaaa/release': ({ body }: { body: unknown }) => {
        sent = body;

        return json(wave({ status: 'released', pickListIds: ['a', 'b'] }));
      },
    });
    open('/waves/wave-0001-aaaa', api);

    await user.click(await screen.findByRole('button', { name: 'Lanzar al almacén' }));
    const dialog = await screen.findByRole('dialog', { name: 'Lanzar la ola al almacén' });
    await user.clear(within(dialog).getByLabelText('Número de listas'));
    await user.type(within(dialog).getByLabelText('Número de listas'), '2');
    await user.click(within(dialog).getByRole('button', { name: 'Lanzar' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(sent).toEqual({ pickLists: 2 });
    expect((await screen.findAllByText('En el almacén')).length).toBeGreaterThan(0);
    expect(screen.queryByRole('button', { name: 'Lanzar al almacén' })).not.toBeInTheDocument();
  });

  it('cancels after confirming, and shows the refusal of the API inside the dialog', async () => {
    const user = userEvent.setup();
    let attempts = 0;
    const api = base(['supervisor'], wave({ status: 'released' }), {
      'POST /api/waves/wave-0001-aaaa/cancel': () => {
        attempts += 1;

        return attempts === 1
          ? problem(403, 'outbound.wave_already_picking')
          : json(wave({ status: 'cancelled' }));
      },
    });
    open('/waves/wave-0001-aaaa', api);

    await user.click(await screen.findByRole('button', { name: 'Cancelar ola' }));
    const dialog = await screen.findByRole('dialog', { name: 'Cancelar la ola wave-000' });
    await user.click(within(dialog).getByRole('button', { name: 'Cancelar la ola' }));
    expect(await within(dialog).findByRole('alert')).toHaveTextContent(
      'outbound.wave_already_picking',
    );

    await user.click(within(dialog).getByRole('button', { name: 'Cancelar la ola' }));
    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect((await screen.findAllByText('Cancelada')).length).toBeGreaterThan(0);
  });

  it('offers no buttons for a completed wave nor to an operator', async () => {
    open('/waves/wave-0001-aaaa', base(['supervisor'], wave({ status: 'completed' })));
    await screen.findByRole('heading', { name: 'Ola wave-000' });
    expect(screen.queryByRole('button', { name: 'Cancelar ola' })).not.toBeInTheDocument();
  });

  it('says so when the wave does not exist', async () => {
    open(
      '/waves/wave-0001-aaaa',
      apiOf(['operator'], {
        ...withSession(['operator']),
        'GET /api/waves/wave-0001-aaaa': () => problem(404, 'shared.not_found'),
        'GET /api/pick-lists?waveId=wave-0001-aaaa': () => json([]),
      }),
    );

    expect(await screen.findByText('No se ha encontrado la ola.')).toBeInTheDocument();
  });
});
