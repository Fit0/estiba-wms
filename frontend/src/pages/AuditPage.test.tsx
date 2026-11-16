import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { paged } from '../testing/paged';
import { apiOf, open, withSession } from '../testing/renderApp';

const entry = (over: Record<string, unknown> = {}) => ({
  id: 'a-1',
  eventType: 'outbound.order_registered',
  occurredAt: '2026-11-10T10:00:00+00:00',
  recordedAt: '2026-11-10T10:00:01+00:00',
  actorId: '0192f2b4-7c3e-7a18-8d5b-3c1f0a8e6b28',
  actorEmail: 'carlos@marea-moda.example',
  subjectId: '5d3c1f0a-7c3e-7a14-8d5b-3c1f0a8e6b2b',
  data: { orderId: '5d3c1f0a-7c3e-7a14-8d5b-3c1f0a8e6b2b', reference: 'PED-0001' },
  ...over,
});

const listUrl = 'GET /api/audit-log?page=1&itemsPerPage=30';
const waveId = '9a8b7c6d-7c3e-7a14-8d5b-3c1f0a8e6b2b';

/** The table once its rows are in: the filter list also holds the names of the events, so it is the table that is read. */
async function loadedTable() {
  const table = await screen.findByRole('table', { name: 'Registro de auditoría' });
  await within(table).findAllByRole('cell', { name: /carlos@marea-moda\.example|Sistema/ });

  return within(table);
}

describe('the audit log', () => {
  it('lists what happened, in the language of the screen, and who did it', async () => {
    open(
      '/audit',
      apiOf(['supervisor'], {
        ...withSession(['supervisor']),
        [listUrl]: () =>
          paged([
            entry(),
            entry({
              id: 'a-2',
              eventType: 'outbound.wave_released',
              actorId: null,
              actorEmail: null,
              subjectId: waveId,
              data: {},
            }),
          ]),
      }),
    );

    const table = await loadedTable();

    expect(table.getByText('Pedido registrado')).toBeInTheDocument();
    expect(table.getByText('Ola lanzada')).toBeInTheDocument();
    expect(table.getByText('carlos@marea-moda.example')).toBeInTheDocument();
    expect(table.getByText('Sistema')).toBeInTheDocument();
  });

  it('names the object by its reference when the event has one, and links to its screen', async () => {
    open(
      '/audit',
      apiOf(['supervisor'], {
        ...withSession(['supervisor']),
        [listUrl]: () =>
          paged([
            entry(),
            entry({ id: 'a-2', eventType: 'outbound.wave_released', subjectId: waveId, data: {} }),
          ]),
      }),
    );

    const order = await screen.findByRole('link', { name: 'Pedido PED-0001' });
    const wave = screen.getByRole('link', { name: 'Ola 9a8b7c6d' });

    expect(order).toHaveAttribute('href', '/orders/5d3c1f0a-7c3e-7a14-8d5b-3c1f0a8e6b2b');
    expect(wave).toHaveAttribute('href', `/waves/${waveId}`);
  });

  it('shows the start of the id of a user that is not found any more', async () => {
    open(
      '/audit',
      apiOf(['supervisor'], {
        ...withSession(['supervisor']),
        [listUrl]: () => paged([entry({ actorEmail: null })]),
      }),
    );

    expect(await screen.findByText('Usuario 0192f2b4')).toBeInTheDocument();
  });

  it('shows what an event carried when asked', async () => {
    const user = userEvent.setup();
    open(
      '/audit',
      apiOf(['supervisor'], {
        ...withSession(['supervisor']),
        [listUrl]: () => paged([entry()]),
      }),
    );

    await user.click(await screen.findByText('Ver datos'));

    expect(screen.getByText(/"reference": "PED-0001"/)).toBeInTheDocument();
  });

  it('shows an event it does not know by its own name', async () => {
    open(
      '/audit',
      apiOf(['supervisor'], {
        ...withSession(['supervisor']),
        [listUrl]: () => paged([entry({ eventType: 'billing.invoice_issued' })]),
      }),
    );

    expect(await screen.findByText('billing.invoice_issued')).toBeInTheDocument();
  });

  it('asks for the type of event the person filters by', async () => {
    const user = userEvent.setup();
    const { router } = open(
      '/audit',
      apiOf(['supervisor'], {
        ...withSession(['supervisor']),
        [listUrl]: () => paged([entry()]),
        [`${listUrl}&eventType=outbound.wave_released`]: () => paged([]),
      }),
    );

    await screen.findByRole('link', { name: 'Pedido PED-0001' });
    await user.click(screen.getByRole('combobox', { name: 'Tipo de suceso' }));
    await user.click(await screen.findByRole('option', { name: 'Ola lanzada' }));

    expect(await screen.findByText('Ningún suceso coincide con los filtros.')).toBeInTheDocument();
    expect(router.state.location.search).toBe('?eventType=outbound.wave_released');
  });

  it('narrows the log to one object from a row, and lets go of the filters', async () => {
    const user = userEvent.setup();
    const subject = '5d3c1f0a-7c3e-7a14-8d5b-3c1f0a8e6b2b';
    const { router } = open(
      '/audit',
      apiOf(['supervisor'], {
        ...withSession(['supervisor']),
        [listUrl]: () =>
          paged([
            entry(),
            entry({ id: 'a-2', subjectId: 'other-subject-id', data: { reference: 'PED-0002' } }),
          ]),
        [`${listUrl}&subjectId=${subject}`]: () => paged([entry()]),
      }),
    );

    await screen.findByRole('link', { name: 'Pedido PED-0002' });
    await user.click(
      screen.getByRole('button', { name: 'Ver solo los sucesos de: Pedido PED-0001' }),
    );

    expect(await screen.findByRole('button', { name: 'Quitar filtros' })).toBeInTheDocument();
    expect(router.state.location.search).toBe(`?subjectId=${subject}`);
    expect(screen.getByRole('textbox', { name: 'Objeto (identificador)' })).toHaveValue(subject);
    await waitFor(() => {
      expect(screen.queryByRole('link', { name: 'Pedido PED-0002' })).not.toBeInTheDocument();
    });

    await user.click(screen.getByRole('button', { name: 'Quitar filtros' }));

    expect(router.state.location.search).toBe('');
    expect(await screen.findByRole('link', { name: 'Pedido PED-0002' })).toBeInTheDocument();
  });

  it('searches by the identifier that is typed', async () => {
    const user = userEvent.setup();
    const { router } = open(
      '/audit',
      apiOf(['supervisor'], {
        ...withSession(['supervisor']),
        [listUrl]: () => paged([entry()]),
        [`${listUrl}&subjectId=abc`]: () => paged([]),
      }),
    );

    await screen.findByRole('link', { name: 'Pedido PED-0001' });
    await user.type(screen.getByRole('textbox', { name: 'Objeto (identificador)' }), ' abc ');
    await user.click(screen.getByRole('button', { name: 'Filtrar' }));

    expect(await screen.findByText('Ningún suceso coincide con los filtros.')).toBeInTheDocument();
    expect(router.state.location.search).toBe('?subjectId=abc');
  });

  it('says so when nothing has happened yet', async () => {
    open(
      '/audit',
      apiOf(['supervisor'], { ...withSession(['supervisor']), [listUrl]: () => paged([]) }),
    );

    const table = await screen.findByRole('table', { name: 'Registro de auditoría' });

    expect(
      await within(table).findByText('Todavía no hay sucesos registrados.'),
    ).toBeInTheDocument();
  });
});
