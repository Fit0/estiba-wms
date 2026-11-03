import { onlineManager } from '@tanstack/react-query';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it } from 'vitest';
import { json, me, problem } from '../testing/fakeApi';
import { product } from '../testing/inboundFixtures';
import { http, HttpResponse, server } from '../testing/msw';
import { apiOf, open, withSession } from '../testing/renderApp';

const mine = me(['operator']).id;
const gtin = '8412345678905';

function line(position: number, location: string, units: number, lot: string | null) {
  return {
    position,
    orderId: 'o-1',
    orderLineNumber: position,
    productId: 'p-1',
    locationId: `l-${String(position)}`,
    locationCode: location,
    lot,
    expiryDate: null,
    quantityToPick: units,
    quantityPicked: 0,
    confirmed: false,
    shortReason: null,
  };
}

/** The list as the API keeps it: a confirmation that gets through changes what the next read says. */
function floor() {
  const state = {
    sent: [] as { position: number; body: unknown }[],
    list: {
      id: 'pl-0001-xxxx',
      waveId: 'wave-1',
      status: 'in_progress',
      operatorId: mine,
      unitsToPick: 6,
      unitsPicked: 0,
      lines: [line(1, 'A-01-01', 4, null), line(2, 'A-02-03', 2, null)],
      createdAt: '2026-11-03T09:00:00+00:00',
    },
  };
  function confirm(position: number) {
    return ({ body }: { body: unknown }) => {
      state.sent.push({ position, body });
      const units = (body as { units: number }).units;
      state.list = {
        ...state.list,
        unitsPicked: state.list.unitsPicked + units,
        lines: state.list.lines.map((l) =>
          l.position === position ? { ...l, confirmed: true, quantityPicked: units } : l,
        ),
      };
      if (state.list.lines.every((l) => l.confirmed)) {
        state.list = { ...state.list, status: 'completed' };
      }

      return json(state.list);
    };
  }
  const api = apiOf(['operator'], {
    ...withSession(['operator']),
    'GET /api/products/p-1': () => json(product),
    'GET /api/pick-lists/pl-0001-xxxx': () => json(state.list),
    'GET /api/pick-lists': () => json([state.list]),
    'POST /api/pick-lists/pl-0001-xxxx/lines/1/confirm': confirm(1),
    'POST /api/pick-lists/pl-0001-xxxx/lines/2/confirm': confirm(2),
  });

  return { state, api };
}

function setOnline(online: boolean): void {
  Object.defineProperty(navigator, 'onLine', { value: online, configurable: true });
  window.dispatchEvent(new Event(online ? 'online' : 'offline'));
}

async function confirmStop(
  user: ReturnType<typeof userEvent.setup>,
  location: string,
  units?: string,
) {
  await user.type(screen.getByLabelText('Ubicación', { exact: true }), location);
  await user.type(screen.getByLabelText('Código de barras del producto', { exact: true }), gtin);
  if (units !== undefined) {
    await user.clear(screen.getByLabelText('Unidades recogidas'));
    await user.type(screen.getByLabelText('Unidades recogidas'), units);
  }
  await user.click(screen.getByRole('button', { name: 'Confirmar parada' }));
}

afterEach(() => {
  setOnline(true);
  onlineManager.setOnline(true);
});

describe('picking without a connection', () => {
  it('saves a confirmation, moves on to the next stop and sends it when the network is back', async () => {
    const user = userEvent.setup();
    const { state, api } = floor();
    open('/picking/pl-0001-xxxx', api);
    await screen.findByText('A-01-01');

    setOnline(false);
    await confirmStop(user, 'A-01-01');

    // The stop is done as far as the operator is concerned, and nothing went to the API.
    expect(await screen.findByText('A-02-03')).toBeInTheDocument();
    expect(await screen.findByText(/Confirmaciones guardadas sin conexión: 1/)).toBeInTheDocument();
    expect(state.sent).toHaveLength(0);

    setOnline(true);

    await waitFor(() => {
      expect(state.sent).toHaveLength(1);
    });
    expect(state.sent[0]).toMatchObject({
      position: 1,
      body: { location: 'A-01-01', gtin, units: 4 },
    });
    await waitFor(() => {
      expect(screen.queryByText(/Confirmaciones guardadas sin conexión/)).not.toBeInTheDocument();
    });
    expect(screen.getByText('A-02-03')).toBeInTheDocument();
  });

  it('sends the stops in the order they were made, and completes the list', async () => {
    const user = userEvent.setup();
    const { state, api } = floor();
    open('/picking/pl-0001-xxxx', api);
    await screen.findByText('A-01-01');
    setOnline(false);

    await confirmStop(user, 'A-01-01');
    await screen.findByText('A-02-03');
    await confirmStop(user, 'A-02-03');

    // The list is complete for the operator before anything is sent.
    expect(await screen.findByText('Lista completada. Buen trabajo.')).toBeInTheDocument();
    expect(await screen.findByText(/Confirmaciones guardadas sin conexión: 2/)).toBeInTheDocument();
    expect(state.sent).toHaveLength(0);

    setOnline(true);

    await waitFor(() => {
      expect(state.sent.map((item) => item.position)).toEqual([1, 2]);
    });
    await waitFor(() => {
      expect(screen.queryByText(/Confirmaciones guardadas sin conexión/)).not.toBeInTheDocument();
    });
    expect(screen.getByText('Lista completada. Buen trabajo.')).toBeInTheDocument();
  });

  it('saves it too when the connection fails while the browser still says it is online', async () => {
    const user = userEvent.setup();
    const { state, api } = floor();
    open('/picking/pl-0001-xxxx', api);
    await screen.findByText('A-01-01');
    server.use(
      http.post('*/api/pick-lists/pl-0001-xxxx/lines/1/confirm', () => HttpResponse.error()),
    );

    await confirmStop(user, 'A-01-01');

    expect(await screen.findByText('A-02-03')).toBeInTheDocument();
    expect(await screen.findByText(/Confirmaciones guardadas sin conexión: 1/)).toBeInTheDocument();
    expect(state.sent).toHaveLength(0);
  });

  it('shows what the server refused, puts the stop back and lets the operator give it up', async () => {
    const user = userEvent.setup();
    const { api } = floor();
    open('/picking/pl-0001-xxxx', api);
    await screen.findByText('A-01-01');
    server.use(
      http.post('*/api/pick-lists/pl-0001-xxxx/lines/1/confirm', () =>
        HttpResponse.json(
          {
            status: 422,
            code: 'outbound.pick_scan_mismatch',
            detail: 'El producto escaneado no coincide con la línea 1.',
            parameters: {},
          },
          { status: 422, headers: { 'Content-Type': 'application/problem+json' } },
        ),
      ),
    );
    setOnline(false);
    await confirmStop(user, 'A-01-01');
    await screen.findByText('A-02-03');

    setOnline(true);

    expect(
      await screen.findByText('El servidor no ha aceptado estas confirmaciones'),
    ).toBeInTheDocument();
    expect(screen.getByText('Lista pl-0001-, parada 1')).toBeInTheDocument();
    expect(
      screen.getByText('El producto escaneado no coincide con la línea 1.'),
    ).toBeInTheDocument();
    // The stop is to do again.
    expect(await screen.findByText('A-01-01', { selector: 'p' })).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Descartar' }));

    expect(
      screen.queryByText('El servidor no ha aceptado estas confirmaciones'),
    ).not.toBeInTheDocument();
  });

  it('explains that a list that was never opened is not on the device', async () => {
    onlineManager.setOnline(false);
    const { api } = floor();
    open('/picking/pl-0001-xxxx', api);

    expect(
      await screen.findByText('Sin conexión y esta lista no está guardada en el dispositivo.'),
    ).toBeInTheDocument();
  });

  it('keeps a 4xx on the form as before, without queueing it', async () => {
    const user = userEvent.setup();
    const { state, api } = floor();
    open('/picking/pl-0001-xxxx', api);
    await screen.findByText('A-01-01');
    server.use(
      http.post('*/api/pick-lists/pl-0001-xxxx/lines/1/confirm', () =>
        problem(422, 'outbound.pick_scan_mismatch'),
      ),
    );

    await confirmStop(user, 'A-01-01');

    expect(await screen.findByRole('alert')).toBeInTheDocument();
    expect(screen.queryByText(/Confirmaciones guardadas sin conexión/)).not.toBeInTheDocument();
    expect(state.sent).toHaveLength(0);
  });
});
