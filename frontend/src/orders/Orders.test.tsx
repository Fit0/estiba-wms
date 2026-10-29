import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { json, problem } from '../testing/fakeApi';
import { product, warehouse } from '../testing/inboundFixtures';
import { paged } from '../testing/paged';
import { apiOf, open, withSession } from '../testing/renderApp';

const order = (overrides: Record<string, unknown> = {}) => ({
  id: 'o-1',
  reference: 'PED-2026-1187',
  warehouseId: 'w-1',
  customer: 'Marea Moda Zaragoza',
  shipTo: { street: 'Calle Mayor 12', postalCode: '50001', city: 'Zaragoza', countryCode: 'ES' },
  priority: 'urgent',
  cutOffAt: '2026-10-30T14:00:00+00:00',
  registeredAt: '2026-10-29T09:00:00+00:00',
  status: 'registered',
  shortageUnits: 0,
  backorderAccepted: false,
  waveId: null,
  lines: [
    {
      lineNumber: 1,
      productId: 'p-1',
      orderedUnits: 24,
      allocatedUnits: 0,
      shortageUnits: 0,
      pickedUnits: 0,
      shippedUnits: 0,
      reservationId: null,
    },
  ],
  ...overrides,
});

const common = {
  'GET /api/warehouses': () => json([warehouse]),
  'GET /api/products/p-1': () => json(product),
};

describe('the orders list', () => {
  it('lists the orders with priority, cut-off, shortage and status', async () => {
    open(
      '/orders',
      apiOf(['operator'], {
        ...withSession(['operator']),
        ...common,
        'GET /api/orders?page=1&itemsPerPage=30': () =>
          paged(
            [
              order(),
              order({ id: 'o-2', reference: 'PED-2', status: 'cancelled', priority: 'low' }),
            ],
            2,
          ),
      }),
    );

    const table = await screen.findByRole('region', { name: 'Pedidos' });
    expect(await within(table).findByText('PED-2026-1187')).toBeInTheDocument();
    expect(within(table).getByText('Urgente')).toBeInTheDocument();
    expect(within(table).getByText('Cancelado')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Nuevo pedido' })).not.toBeInTheDocument();
  });

  it('filters by status on the server and keeps it in the address', async () => {
    const user = userEvent.setup();
    const api = apiOf(['operator'], {
      ...withSession(['operator']),
      ...common,
      'GET /api/orders?page=1&itemsPerPage=30': () => paged([order()]),
      'GET /api/orders?page=1&itemsPerPage=30&status=shipped': () =>
        paged([order({ id: 'o-3', reference: 'PED-3', status: 'shipped' })]),
    });
    const { router } = open('/orders', api);

    await screen.findByText('PED-2026-1187');
    await user.click(screen.getByRole('combobox', { name: 'Estado' }));
    await user.click(await screen.findByRole('option', { name: 'Enviado' }));

    expect(await screen.findByText('PED-3')).toBeInTheDocument();
    expect(router.state.location.search).toContain('status=shipped');
  });
});

describe('registering an order', () => {
  it('registers an order for the active warehouse with a product found by typing', async () => {
    const user = userEvent.setup();
    let sent: unknown;
    const api = apiOf(['supervisor'], {
      ...withSession(['supervisor']),
      ...common,
      'GET /api/orders?page=1&itemsPerPage=30': () => paged([]),
      'GET /api/products?page=1&itemsPerPage=8&q=camisa': () => paged([product], 1),
      'POST /api/orders': ({ body }) => {
        sent = body;

        return json(order({ id: 'o-new', reference: 'PED-NEW' }), 201);
      },
      'GET /api/orders/o-new': () => json(order({ id: 'o-new', reference: 'PED-NEW' })),
    });
    open('/orders', api);

    await user.click(await screen.findByRole('button', { name: 'Nuevo pedido' }));
    const dialog = await screen.findByRole('dialog', { name: 'Nuevo pedido' });
    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));
    expect(await within(dialog).findByText('Elige un producto.')).toBeInTheDocument();
    expect(api.calls).not.toContain('POST /api/orders');

    await user.type(within(dialog).getByLabelText('Referencia'), 'PED-NEW');
    await user.type(within(dialog).getByLabelText('Cliente'), 'Marea Moda');
    await user.type(within(dialog).getByLabelText('Calle'), 'Calle Mayor 12');
    await user.type(within(dialog).getByLabelText('Código postal'), '50001');
    await user.type(within(dialog).getByLabelText('Ciudad'), 'Zaragoza');
    fireEvent.change(within(dialog).getByLabelText('Hora de corte'), {
      target: { value: '2026-10-30T14:00' },
    });
    const line = within(dialog).getByRole('group', { name: 'Línea 1' });
    await user.type(within(line).getByRole('searchbox'), 'camisa');
    await user.click(await within(line).findByRole('button', { name: /CAM-LIN/ }));
    await user.type(within(line).getByLabelText('Unidades'), '24');
    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));

    expect(await screen.findByRole('heading', { name: /PED-NEW/ })).toBeInTheDocument();
    expect(sent).toMatchObject({
      reference: 'PED-NEW',
      warehouseId: 'w-1',
      customer: 'Marea Moda',
      shipTo: {
        street: 'Calle Mayor 12',
        postalCode: '50001',
        city: 'Zaragoza',
        countryCode: 'ES',
      },
      priority: 'normal',
      lines: [{ productId: 'p-1', units: 24 }],
    });
  });

  it('shows what the API says when the reference is taken, inside the form', async () => {
    const user = userEvent.setup();
    const api = apiOf(['supervisor'], {
      ...withSession(['supervisor']),
      ...common,
      'GET /api/orders?page=1&itemsPerPage=30': () => paged([]),
      'GET /api/products?page=1&itemsPerPage=8&q=camisa': () => paged([product], 1),
      'POST /api/orders': () => problem(403, 'outbound.reference_taken'),
    });
    open('/orders', api);

    await user.click(await screen.findByRole('button', { name: 'Nuevo pedido' }));
    const dialog = await screen.findByRole('dialog', { name: 'Nuevo pedido' });
    await user.type(within(dialog).getByLabelText('Referencia'), 'X-1');
    await user.type(within(dialog).getByLabelText('Cliente'), 'Cliente');
    await user.type(within(dialog).getByLabelText('Calle'), 'Calle');
    await user.type(within(dialog).getByLabelText('Código postal'), '1');
    await user.type(within(dialog).getByLabelText('Ciudad'), 'Ciudad');
    const line = within(dialog).getByRole('group', { name: 'Línea 1' });
    await user.type(within(line).getByRole('searchbox'), 'camisa');
    await user.click(await within(line).findByRole('button', { name: /CAM-LIN/ }));
    await user.type(within(line).getByLabelText('Unidades'), '1');
    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));

    expect(await within(dialog).findByRole('alert')).toHaveTextContent('outbound.reference_taken');
  });

  it('adds and removes lines', async () => {
    const user = userEvent.setup();
    open(
      '/orders',
      apiOf(['supervisor'], {
        ...withSession(['supervisor']),
        ...common,
        'GET /api/orders?page=1&itemsPerPage=30': () => paged([]),
      }),
    );

    await user.click(await screen.findByRole('button', { name: 'Nuevo pedido' }));
    const dialog = await screen.findByRole('dialog', { name: 'Nuevo pedido' });
    expect(within(dialog).getByRole('button', { name: 'Quitar la línea 1' })).toBeDisabled();
    await user.click(within(dialog).getByRole('button', { name: 'Añadir línea' }));
    expect(within(dialog).getByRole('group', { name: 'Línea 2' })).toBeInTheDocument();
    await user.click(within(dialog).getByRole('button', { name: 'Quitar la línea 2' }));
    expect(within(dialog).queryByRole('group', { name: 'Línea 2' })).not.toBeInTheDocument();
  });
});

describe('one order', () => {
  function page(roles: ('operator' | 'supervisor')[], current = order(), extra = {}) {
    return apiOf(roles, {
      ...withSession(roles),
      ...common,
      'GET /api/orders/o-1': () => json(current),
      ...extra,
    });
  }

  it('shows its data and its lines', async () => {
    open(
      '/orders/o-1',
      page(['operator'], order({ status: 'partially_allocated', shortageUnits: 4 })),
    );

    expect(
      await screen.findByRole('heading', { name: 'PED-2026-1187 · Marea Moda Zaragoza' }),
    ).toBeInTheDocument();
    expect(screen.getByText('Calle Mayor 12, 50001 Zaragoza (ES)')).toBeInTheDocument();
    const lines = await screen.findByRole('region', { name: 'Líneas del pedido' });
    expect(await within(lines).findByText('CAM-LIN · Camisa de lino')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Reservar stock' })).not.toBeInTheDocument();
  });

  it('lets a supervisor reserve stock', async () => {
    const user = userEvent.setup();
    const api = page(['supervisor'], order(), {
      'POST /api/orders/o-1/allocate': () =>
        json(order({ status: 'allocated', lines: [{ ...order().lines[0], allocatedUnits: 24 }] })),
    });
    open('/orders/o-1', api);

    await user.click(await screen.findByRole('button', { name: 'Reservar stock' }));

    expect(await screen.findAllByText('Reservado')).not.toHaveLength(0);
    await waitFor(() => {
      expect(screen.queryByRole('button', { name: 'Reservar stock' })).not.toBeInTheDocument();
    });
    expect(api.calls).toContain('POST /api/orders/o-1/allocate');
  });

  it('offers the backorder only to a partly allocated order, and accepts it', async () => {
    const user = userEvent.setup();
    const api = page(['supervisor'], order({ status: 'partially_allocated', shortageUnits: 4 }), {
      'POST /api/orders/o-1/accept-backorder': () =>
        json(order({ status: 'partially_allocated', shortageUnits: 4, backorderAccepted: true })),
    });
    open('/orders/o-1', api);

    await user.click(await screen.findByRole('button', { name: 'Aceptar backorder' }));

    await waitFor(() => {
      expect(screen.queryByRole('button', { name: 'Aceptar backorder' })).not.toBeInTheDocument();
    });
  });

  it('cancels after confirming, and shows the refusal of the API inside the dialog', async () => {
    const user = userEvent.setup();
    let attempts = 0;
    const api = page(['supervisor'], order(), {
      'POST /api/orders/o-1/cancel': () => {
        attempts += 1;

        return attempts === 1
          ? problem(403, 'outbound.order_already_picked')
          : json(order({ status: 'cancelled' }));
      },
    });
    open('/orders/o-1', api);

    await user.click(await screen.findByRole('button', { name: 'Cancelar pedido' }));
    const dialog = await screen.findByRole('dialog', { name: 'Cancelar PED-2026-1187' });
    await user.click(within(dialog).getByRole('button', { name: 'Cancelar el pedido' }));
    expect(await within(dialog).findByRole('alert')).toHaveTextContent(
      'outbound.order_already_picked',
    );

    await user.click(within(dialog).getByRole('button', { name: 'Cancelar el pedido' }));
    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect((await screen.findAllByText('Cancelado')).length).toBeGreaterThan(0);
  });

  it('offers no actions for an order that is already shipped', async () => {
    open('/orders/o-1', page(['supervisor'], order({ status: 'shipped' })));

    await screen.findByRole('heading', { name: /PED-2026-1187/ });
    expect(screen.queryByRole('button', { name: 'Cancelar pedido' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Reservar stock' })).not.toBeInTheDocument();
  });

  it('says so when the order does not exist', async () => {
    open(
      '/orders/o-1',
      apiOf(['operator'], {
        ...withSession(['operator']),
        ...common,
        'GET /api/orders/o-1': () => problem(404, 'shared.not_found'),
      }),
    );

    expect(await screen.findByText('No se ha encontrado el pedido.')).toBeInTheDocument();
  });
});
