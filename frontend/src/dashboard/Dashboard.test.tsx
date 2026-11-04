import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { periodStart } from '../api/kpis';
import { json, problem } from '../testing/fakeApi';
import { kpis, noPicking } from '../testing/kpiFixtures';
import { http, server } from '../testing/msw';
import { apiOf, open, withSession } from '../testing/renderApp';

const asked: URL[] = [];

function serve(answer: () => Response) {
  asked.length = 0;
  server.use(
    http.get(`${globalThis.location.origin}/api/kpis`, ({ request }) => {
      asked.push(new URL(request.url));

      return answer();
    }),
  );
}

function card(name: string): HTMLElement {
  const article = screen.getByRole('heading', { name }).closest('article');
  if (article === null) {
    throw new Error(`No card named ${name}`);
  }

  return article;
}

const supervisor = () => apiOf(['supervisor'], withSession(['supervisor']));

describe('the dashboard', () => {
  it('shows the figures of the last 7 days to a supervisor', async () => {
    const api = supervisor();
    open('/', api);
    serve(() => json(kpis));

    expect(await screen.findByRole('heading', { name: 'Pedidos' })).toBeInTheDocument();
    await screen.findByText('9');

    expect(within(card('Pedidos abiertos')).getByText('4')).toBeInTheDocument();
    expect(within(card('Pedidos enviados')).getByText('7')).toBeInTheDocument();
    expect(within(card('Hojas completadas')).getByText('45 líneas')).toBeInTheDocument();
    expect(within(card('Líneas por hora')).getByText('30')).toBeInTheDocument();
    expect(within(card('Minutos por hoja')).getByText('30,5 min')).toBeInTheDocument();
    expect(within(card('Unidades no encontradas')).getByText('0,8 %')).toBeInTheDocument();
    expect(
      within(card('Unidades no encontradas')).getByText('10 unidades en 2 líneas'),
    ).toBeInTheDocument();
    expect(within(card('Unidades en el almacén')).getByText('5400')).toBeInTheDocument();
    expect(within(card('Dañadas')).getByText('20')).toBeInTheDocument();
    expect(within(card('Ocupación del almacenaje')).getByText('75 %')).toBeInTheDocument();
    expect(
      within(card('Ocupación del almacenaje')).getByText('3 de 4 ubicaciones con stock'),
    ).toBeInTheDocument();

    const request = asked[0];
    expect(request?.searchParams.has('until')).toBe(false);
    const from = new Date(request?.searchParams.get('from') ?? '').getTime();
    expect(Date.now() - from).toBeGreaterThan(7 * 24 * 3_600_000 - 60_000);
    expect(Date.now() - from).toBeLessThan(7 * 24 * 3_600_000 + 60_000);
    expect(screen.getByRole('button', { name: '7 días' })).toHaveAttribute('aria-pressed', 'true');
  });

  it('asks again for the period that is chosen', async () => {
    const user = userEvent.setup();
    open('/', supervisor());
    serve(() => json(kpis));
    await screen.findByText('9');

    await user.click(screen.getByRole('button', { name: '24 horas' }));

    expect(screen.getByRole('button', { name: '24 horas' })).toHaveAttribute(
      'aria-pressed',
      'true',
    );
    expect(screen.getByRole('button', { name: '7 días' })).toHaveAttribute('aria-pressed', 'false');
    await screen.findByText('9');
    const last = asked[asked.length - 1];
    const hours =
      (Date.now() - new Date(last?.searchParams.get('from') ?? '').getTime()) / 3_600_000;
    expect(hours).toBeGreaterThan(23.9);
    expect(hours).toBeLessThan(24.1);
  });

  it('shows a dash, and says why, where the API had nothing to divide by', async () => {
    open('/', supervisor());
    serve(() => json(noPicking));

    await screen.findByText('9');

    expect(within(card('Líneas por hora')).getByText('—')).toBeInTheDocument();
    expect(
      within(card('Líneas por hora')).getByText('Sin datos en el periodo'),
    ).toBeInTheDocument();
    expect(within(card('Minutos por hoja')).getByText('—')).toBeInTheDocument();
    expect(within(card('Unidades no encontradas')).getByText('—')).toBeInTheDocument();
    expect(within(card('Ocupación del almacenaje')).getByText('—')).toBeInTheDocument();
    expect(within(card('Hojas completadas')).getByText('0')).toBeInTheDocument();
  });

  it('says when the figures could not be loaded and tries again on request', async () => {
    const user = userEvent.setup();
    open('/', supervisor());
    serve(() => problem(500, 'shared.internal_error'));

    const alert = await screen.findByRole('alert');
    expect(alert).toHaveTextContent('No se han podido cargar las cifras.');

    serve(() => json(kpis));
    await user.click(within(alert).getByRole('button', { name: 'Reintentar' }));

    expect(await screen.findByText('9')).toBeInTheDocument();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    expect(asked).toHaveLength(1);
  });

  it('refreshes by hand', async () => {
    const user = userEvent.setup();
    open('/', supervisor());
    serve(() => json(kpis));
    await screen.findByText('9');
    expect(asked).toHaveLength(1);

    await user.click(screen.getByRole('button', { name: 'Actualizar' }));

    await screen.findByText(/Actualizado a las/);
    expect(asked.length).toBeGreaterThan(1);
  });

  it('is not shown to an operator, who is not even allowed to ask', async () => {
    open('/', apiOf(['operator'], withSession(['operator'])));
    serve(() => json(kpis));

    expect(await screen.findByText('Bienvenido a Marea Moda.')).toBeInTheDocument();

    expect(screen.queryByRole('heading', { name: 'Pedidos' })).not.toBeInTheDocument();
    expect(asked).toHaveLength(0);
  });
});

describe('the start of a period', () => {
  it('counts back from now and has no fraction of a second', () => {
    const now = new Date('2026-11-03T10:00:00.789Z');

    expect(periodStart('day', now)).toBe('2026-11-02T10:00:00Z');
    expect(periodStart('week', now)).toBe('2026-10-27T10:00:00Z');
    expect(periodStart('month', now)).toBe('2026-10-04T10:00:00Z');
  });
});
