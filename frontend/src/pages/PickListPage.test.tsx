import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { json, me, problem } from '../testing/fakeApi';
import { product } from '../testing/inboundFixtures';
import { apiOf, open, withSession } from '../testing/renderApp';

const mine = me(['operator']).id;

function line(position: number, location: string, units: number, lot: string | null) {
  return {
    position,
    orderId: 'o-1',
    orderLineNumber: position,
    productId: 'p-1',
    locationId: `l-${String(position)}`,
    locationCode: location,
    lot,
    expiryDate: lot === null ? null : '2027-03-31',
    quantityToPick: units,
    quantityPicked: 0,
    confirmed: false,
    shortReason: null,
  };
}

function aList(status: string, operatorId: string | null) {
  return {
    id: 'pl-0001-xxxx',
    waveId: 'wave-1',
    status,
    operatorId,
    unitsToPick: 6,
    unitsPicked: 0,
    lines: [line(1, 'A-01-01', 4, 'L-9'), line(2, 'A-02-03', 2, null)],
    createdAt: '2026-10-30T09:00:00+00:00',
  };
}

/** The list as the API keeps it: a start and a confirmation change what the next read says. */
function floor(initial: ReturnType<typeof aList>) {
  const state = { list: initial, confirmations: [] as unknown[] };
  const api = apiOf(['operator'], {
    ...withSession(['operator']),
    'GET /api/products/p-1': () => json(product),
    'GET /api/pick-lists/pl-0001-xxxx': () => json(state.list),
    'POST /api/pick-lists/pl-0001-xxxx/start': () => {
      state.list = { ...state.list, status: 'in_progress', operatorId: mine };

      return json(state.list);
    },
    'POST /api/pick-lists/pl-0001-xxxx/lines/1/confirm': ({ body }) => {
      state.confirmations.push(body);
      const sent = body as { units: number };
      state.list = {
        ...state.list,
        unitsPicked: sent.units,
        lines: state.list.lines.map((l) =>
          l.position === 1 ? { ...l, confirmed: true, quantityPicked: sent.units } : l,
        ),
      };

      return json(state.list);
    },
    'POST /api/pick-lists/pl-0001-xxxx/lines/2/confirm': ({ body }) => {
      state.confirmations.push(body);
      state.list = {
        ...state.list,
        status: 'completed',
        lines: state.list.lines.map((l) => ({ ...l, confirmed: true })),
      };

      return json(state.list);
    },
  });

  return { state, api };
}

describe('PickListPage', () => {
  it('starts a list and walks its stops in order until it is completed', async () => {
    const user = userEvent.setup();
    const { state, api } = floor(aList('generated', null));
    open('/picking/pl-0001-xxxx', api);

    expect(
      await screen.findByText('Paradas: 2. Unidades: 6. Al empezar, la lista queda a tu nombre.'),
    ).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Empezar la lista' }));

    // First stop: a location, a product, a lot.
    expect(await screen.findByText('A-01-01')).toBeInTheDocument();
    expect(await screen.findByText(/CAM-LIN · Camisa de lino/)).toBeInTheDocument();
    expect(screen.getByText('Coge 4 unidades')).toBeInTheDocument();
    expect(screen.getByText(/Lote L-9/)).toBeInTheDocument();

    await user.type(screen.getByLabelText('Ubicación'), 'a-01-01');
    await user.type(screen.getByLabelText('Código de barras del producto'), '8412345678905');
    await user.type(screen.getByLabelText('Lote'), 'L-9');
    await user.click(screen.getByRole('button', { name: 'Confirmar parada' }));

    // Second stop has no lot field.
    expect(await screen.findByText('A-02-03')).toBeInTheDocument();
    expect(screen.queryByLabelText('Lote')).not.toBeInTheDocument();
    expect(state.confirmations[0]).toEqual({
      location: 'a-01-01',
      gtin: '8412345678905',
      units: 4,
      lot: 'L-9',
    });

    await user.type(screen.getByLabelText('Ubicación'), 'A-02-03');
    await user.type(screen.getByLabelText('Código de barras del producto'), '8412345678905');
    await user.click(screen.getByRole('button', { name: 'Confirmar parada' }));

    expect(await screen.findByText('Lista completada. Buen trabajo.')).toBeInTheDocument();
    expect(state.confirmations[1]).toEqual({
      location: 'A-02-03',
      gtin: '8412345678905',
      units: 2,
    });
  });

  it('confirms with the Enter key a scanner presses after each code', async () => {
    const user = userEvent.setup();
    const { state, api } = floor(aList('in_progress', mine));
    open('/picking/pl-0001-xxxx', api);
    await screen.findByText('A-01-01');

    await user.type(screen.getByLabelText('Ubicación'), 'A-01-01{Enter}');
    expect(screen.getByLabelText('Código de barras del producto')).toHaveFocus();
    await user.type(screen.getByLabelText('Código de barras del producto'), '8412345678905{Enter}');
    expect(screen.getByLabelText('Lote')).toHaveFocus();
    await user.type(screen.getByLabelText('Lote'), 'L-9{Enter}');
    expect(screen.getByLabelText('Unidades recogidas')).toHaveFocus();
    await user.keyboard('{Enter}');

    expect(await screen.findByText('A-02-03')).toBeInTheDocument();
    expect(state.confirmations).toHaveLength(1);
  });

  it('says what is wrong before sending anything', async () => {
    const user = userEvent.setup();
    const { state, api } = floor(aList('in_progress', mine));
    open('/picking/pl-0001-xxxx', api);
    await screen.findByText('A-01-01');

    await user.type(screen.getByLabelText('Ubicación'), 'B-09-09');
    await user.type(screen.getByLabelText('Código de barras del producto'), '12');
    await user.type(screen.getByLabelText('Lote'), 'L-1');
    await user.click(screen.getByRole('button', { name: 'Confirmar parada' }));

    expect(await screen.findByText('No es la ubicación de esta parada.')).toBeInTheDocument();
    expect(screen.getByText('Escribe un código de 8 a 14 cifras.')).toBeInTheDocument();
    expect(screen.getByText('No es el lote de esta parada.')).toBeInTheDocument();
    expect(state.confirmations).toHaveLength(0);
  });

  it('asks why when fewer units are picked than asked, and sends the reason', async () => {
    const user = userEvent.setup();
    const { state, api } = floor(aList('in_progress', mine));
    open('/picking/pl-0001-xxxx', api);
    await screen.findByText('A-01-01');

    await user.type(screen.getByLabelText('Ubicación'), 'A-01-01');
    await user.type(screen.getByLabelText('Código de barras del producto'), '8412345678905');
    await user.type(screen.getByLabelText('Lote'), 'L-9');
    expect(screen.queryByLabelText('Motivo de las unidades que faltan')).not.toBeInTheDocument();
    await user.clear(screen.getByLabelText('Unidades recogidas'));
    await user.type(screen.getByLabelText('Unidades recogidas'), '3');
    await user.click(screen.getByRole('button', { name: 'Confirmar parada' }));
    expect(await screen.findByText('Indica por qué faltan unidades.')).toBeInTheDocument();
    expect(state.confirmations).toHaveLength(0);

    await user.type(screen.getByLabelText('Motivo de las unidades que faltan'), 'Hueco vacío');
    await user.click(screen.getByRole('button', { name: 'Confirmar parada' }));

    expect(await screen.findByText('A-02-03')).toBeInTheDocument();
    expect(state.confirmations[0]).toMatchObject({ units: 3, reason: 'Hueco vacío' });
  });

  it('refuses more units than asked', async () => {
    const user = userEvent.setup();
    const { api } = floor(aList('in_progress', mine));
    open('/picking/pl-0001-xxxx', api);
    await screen.findByText('A-01-01');

    await user.clear(screen.getByLabelText('Unidades recogidas'));
    await user.type(screen.getByLabelText('Unidades recogidas'), '9');
    await user.click(screen.getByRole('button', { name: 'Confirmar parada' }));

    expect(await screen.findByText('Escribe un número entre 0 y 4.')).toBeInTheDocument();
  });

  it('shows what the API says when it does not accept the scan', async () => {
    const user = userEvent.setup();
    const api = apiOf(['operator'], {
      ...withSession(['operator']),
      'GET /api/products/p-1': () => json(product),
      'GET /api/pick-lists/pl-0001-xxxx': () => json(aList('in_progress', mine)),
      'POST /api/pick-lists/pl-0001-xxxx/lines/1/confirm': () =>
        problem(422, 'outbound.pick_scan_mismatch'),
    });
    open('/picking/pl-0001-xxxx', api);
    await screen.findByText('A-01-01');

    await user.type(screen.getByLabelText('Ubicación'), 'A-01-01');
    await user.type(screen.getByLabelText('Código de barras del producto'), '8412345678900');
    await user.type(screen.getByLabelText('Lote'), 'L-9');
    await user.click(screen.getByRole('button', { name: 'Confirmar parada' }));

    expect(await screen.findByRole('alert')).toBeInTheDocument();
    expect(screen.getByLabelText('Ubicación')).toHaveValue('A-01-01');
  });

  it('does not let an operator walk a list that another one has', async () => {
    const { api } = floor(aList('in_progress', 'someone-else'));
    open('/picking/pl-0001-xxxx', api);

    expect(
      await screen.findByText('Esta lista la está preparando otro operario.'),
    ).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Confirmar parada' })).not.toBeInTheDocument();
  });

  it('says so for a cancelled list and for one that does not exist', async () => {
    const { api } = floor(aList('cancelled', mine));
    open('/picking/pl-0001-xxxx', api);
    expect(await screen.findByText('La lista se ha cancelado.')).toBeInTheDocument();
  });

  it('says so when the list does not exist', async () => {
    const api = apiOf(['operator'], {
      ...withSession(['operator']),
      'GET /api/pick-lists/nope': () => problem(404, 'outbound.pick_list_not_found'),
    });
    open('/picking/nope', api);

    expect(
      await screen.findByRole('heading', { name: 'No se ha encontrado la lista.' }),
    ).toBeInTheDocument();
  });
});
