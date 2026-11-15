import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import type { Role } from '../auth/roles';
import { json } from '../testing/fakeApi';
import { location, product, putawayTask, warehouse } from '../testing/inboundFixtures';
import { paged } from '../testing/paged';
import { apiOf, open, withSession } from '../testing/renderApp';

const locations = [
  location('D-01', 'dock'),
  location('A-01-01', 'reserve'),
  location('B-02-03', 'pick_face'),
];

function api(roles: Role[], tasks: () => Response, extra = {}) {
  return apiOf(roles, {
    ...withSession(roles),
    'GET /api/warehouses': () => json([warehouse]),
    'GET /api/warehouses/w-1/locations?page=1&itemsPerPage=100&status=active': () =>
      paged(locations),
    'GET /api/products/p-1': () => json(product),
    'GET /api/putaway-tasks?page=1&itemsPerPage=30&status=pending': tasks,
    ...extra,
  });
}

describe('the putaway tasks', () => {
  it('shows what waits at the dock, with the codes of the locations, to an operator', async () => {
    open(
      '/putaway',
      api(['operator'], () => paged([putawayTask()])),
    );

    const table = await screen.findByRole('region', { name: 'Tareas de ubicación' });
    expect(await within(table).findByText('CAM-LIN · Camisa de lino')).toBeInTheDocument();
    // The codes come from a second request, so they replace the short ids a moment later.
    expect(await within(table).findByText('D-01')).toBeInTheDocument();
    expect(await within(table).findByText('A-01-01')).toBeInTheDocument();
    expect(within(table).getByText('Pendiente')).toBeInTheDocument();
    expect(
      within(table).getByRole('button', { name: 'Ubicar CAM-LIN · Camisa de lino' }),
    ).toBeInTheDocument();
  });

  it('says so when nothing waits', async () => {
    open(
      '/putaway',
      api(['operator'], () => paged([])),
    );

    expect(await screen.findByText('No hay mercancía esperando en un muelle.')).toBeInTheDocument();
  });

  it('puts the goods in the suggested location without asking for a reason', async () => {
    const user = userEvent.setup();
    const sent: unknown[] = [];
    open(
      '/putaway',
      api(['operator'], () => paged([putawayTask()]), {
        'POST /api/putaway-tasks/t-1/confirm': ({ body }: { body: unknown }) => {
          sent.push(body);

          return json(putawayTask({ status: 'confirmed', confirmedLocationId: 'l-A-01-01' }));
        },
      }),
    );

    await user.click(
      await screen.findByRole('button', { name: 'Ubicar CAM-LIN · Camisa de lino' }),
    );
    const dialog = await screen.findByRole('dialog', { name: 'Ubicar CAM-LIN · Camisa de lino' });
    expect(
      within(dialog).getByRole('combobox', { name: 'Ubicación de destino' }),
    ).toHaveTextContent('A-01-01');
    expect(within(dialog).queryByLabelText(/Motivo/)).not.toBeInTheDocument();
    await user.click(within(dialog).getByRole('button', { name: 'Confirmar ubicación' }));

    await waitFor(() => {
      expect(sent).toEqual([{ locationId: 'l-A-01-01' }]);
    });
    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
  });

  it('asks why when the goods go to another location than the suggested one', async () => {
    const user = userEvent.setup();
    const sent: unknown[] = [];
    open(
      '/putaway',
      api(['operator'], () => paged([putawayTask()]), {
        'POST /api/putaway-tasks/t-1/confirm': ({ body }: { body: unknown }) => {
          sent.push(body);

          return json(putawayTask({ status: 'confirmed', confirmedLocationId: 'l-B-02-03' }));
        },
      }),
    );

    await user.click(
      await screen.findByRole('button', { name: 'Ubicar CAM-LIN · Camisa de lino' }),
    );
    const dialog = await screen.findByRole('dialog');
    await user.click(within(dialog).getByRole('combobox', { name: 'Ubicación de destino' }));
    await user.click(await screen.findByRole('option', { name: /B-02-03/ }));

    await user.click(within(dialog).getByRole('button', { name: 'Confirmar ubicación' }));
    expect(await within(dialog).findByText('Escribe un valor.')).toBeInTheDocument();
    expect(sent).toEqual([]);

    await user.type(
      within(dialog).getByLabelText('Motivo de no usar la ubicación sugerida'),
      'La sugerida está llena',
    );
    await user.click(within(dialog).getByRole('button', { name: 'Confirmar ubicación' }));

    await waitFor(() => {
      expect(sent).toEqual([{ locationId: 'l-B-02-03', reason: 'La sugerida está llena' }]);
    });
  });

  it('shows what the API refuses inside the dialog', async () => {
    const user = userEvent.setup();
    open(
      '/putaway',
      api(['operator'], () => paged([putawayTask()]), {
        'POST /api/putaway-tasks/t-1/confirm': () =>
          new Response(
            JSON.stringify({
              status: 422,
              code: 'inbound.putaway_destination_invalid',
              detail: 'La mercancía no puede ir a la ubicación 01a1d6da-38c9: no hay sitio.',
              parameters: { locationId: 'l-A-01-01', reason: 'no_room' },
            }),
            { status: 422, headers: { 'Content-Type': 'application/problem+json' } },
          ),
      }),
    );

    await user.click(
      await screen.findByRole('button', { name: 'Ubicar CAM-LIN · Camisa de lino' }),
    );
    const dialog = await screen.findByRole('dialog');
    await user.click(within(dialog).getByRole('button', { name: 'Confirmar ubicación' }));

    const alert = await within(dialog).findByRole('alert');
    expect(alert).toHaveTextContent(
      'No se puede ubicar en A-01-01: no hay sitio para estas unidades.',
    );
    expect(alert).not.toHaveTextContent('l-A-01-01');
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });

  it('splits a task, and only accepts a number that leaves units in both', async () => {
    const user = userEvent.setup();
    const sent: unknown[] = [];
    open(
      '/putaway',
      api(['operator'], () => paged([putawayTask()]), {
        'POST /api/putaway-tasks/t-1/split': ({ body }: { body: unknown }) => {
          sent.push(body);

          return json(putawayTask({ id: 't-2', units: 40 }));
        },
      }),
    );

    await user.click(
      await screen.findByRole('button', { name: 'Dividir CAM-LIN · Camisa de lino' }),
    );
    const dialog = await screen.findByRole('dialog', { name: 'Dividir CAM-LIN · Camisa de lino' });
    expect(within(dialog).getByText(/La tarea tiene 100 unidades/)).toBeInTheDocument();

    await user.type(within(dialog).getByLabelText('Unidades para la tarea nueva'), '100');
    await user.click(within(dialog).getByRole('button', { name: 'Dividir tarea' }));
    expect(
      await within(dialog).findByText('Cada tarea debe quedarse con al menos una unidad.'),
    ).toBeInTheDocument();
    expect(sent).toEqual([]);

    await user.clear(within(dialog).getByLabelText('Unidades para la tarea nueva'));
    await user.type(within(dialog).getByLabelText('Unidades para la tarea nueva'), '40');
    await user.click(within(dialog).getByRole('button', { name: 'Dividir tarea' }));

    await waitFor(() => {
      expect(sent).toEqual([{ units: 40 }]);
    });
  });

  it('offers no split for a single unit', async () => {
    open(
      '/putaway',
      api(['operator'], () => paged([putawayTask({ units: 1 })])),
    );

    await screen.findByRole('button', { name: 'Ubicar CAM-LIN · Camisa de lino' });
    expect(screen.queryByRole('button', { name: /Dividir/ })).not.toBeInTheDocument();
  });

  it('says that the stock is still moving after a task is confirmed', async () => {
    const calls = api(['operator'], () => paged([]), {
      'GET /api/putaway-tasks?page=1&itemsPerPage=30&status=confirmed': () =>
        paged([
          putawayTask({
            status: 'confirmed',
            confirmedLocationId: 'l-A-01-01',
            confirmedAt: '2026-10-28T09:00:00+00:00',
            stockMoved: false,
          }),
        ]),
    });
    open('/putaway?status=confirmed', calls);

    expect(await screen.findByText('Moviendo el stock…')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Ubicar/ })).not.toBeInTheDocument();
  });

  it('filters by receipt and offers a way back to all of them', async () => {
    const user = userEvent.setup();
    const calls = api(['operator'], () => paged([]), {
      'GET /api/putaway-tasks?page=1&itemsPerPage=30&receiptId=r-1': () => paged([putawayTask()]),
      'GET /api/putaway-tasks?page=1&itemsPerPage=30': () => paged([putawayTask()]),
    });
    open('/putaway?receipt=r-1&status=all', calls);

    expect(await screen.findByText('Solo las tareas de una recepción.')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Ver la recepción' })).toHaveAttribute(
      'href',
      '/receipts/r-1',
    );
    await user.click(screen.getByRole('button', { name: 'Ver todas' }));
    await waitFor(() => {
      expect(screen.queryByText('Solo las tareas de una recepción.')).not.toBeInTheDocument();
    });
  });
});
