import { screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { json, me, problem } from '../testing/fakeApi';
import { apiOf, open, withSession } from '../testing/renderApp';
import { currentDensity } from '../theme/density';

const mineId = me(['operator']).id;

function list(
  id: string,
  status: string,
  operatorId: string | null,
  picked: number,
  total: number,
) {
  return {
    id,
    waveId: 'wave-1',
    status,
    operatorId,
    unitsToPick: total,
    unitsPicked: picked,
    lines: [],
    createdAt: '2026-10-30T09:00:00+00:00',
  };
}

const lists = [
  list('mine-0001-aaaa', 'in_progress', mineId, 12, 40),
  list('free-0001-bbbb', 'generated', null, 0, 25),
  list('other-001-cccc', 'in_progress', 'someone-else', 5, 10),
  list('done-0001-dddd', 'completed', mineId, 8, 8),
];

describe('PickingPage', () => {
  it('splits the active lists into the ones of the operator and the ones nobody has taken', async () => {
    open(
      '/picking',
      apiOf(['operator'], {
        ...withSession(['operator']),
        'GET /api/pick-lists': () => json(lists),
      }),
    );

    const mine = await screen.findByRole('region', { name: 'Mis listas' });
    expect(within(mine).getByText('Lista mine-000')).toBeInTheDocument();
    expect(within(mine).getByText('12 de 40 unidades')).toBeInTheDocument();
    expect(within(mine).getByText('En curso')).toBeInTheDocument();

    const free = screen.getByRole('region', { name: 'Listas sin asignar' });
    expect(within(free).getByText('Lista free-000')).toBeInTheDocument();
    expect(within(free).getByText('Generada')).toBeInTheDocument();

    // Someone else's list and a completed one are not the operator's business.
    expect(screen.queryByText('Lista other-00')).not.toBeInTheDocument();
    expect(screen.queryByText('Lista done-000')).not.toBeInTheDocument();
  });

  it('says so when there is nothing to pick', async () => {
    open(
      '/picking',
      apiOf(['operator'], { ...withSession(['operator']), 'GET /api/pick-lists': () => json([]) }),
    );

    expect(await screen.findByText('No tienes ninguna lista asignada.')).toBeInTheDocument();
    expect(screen.getByText('No hay listas esperando operario.')).toBeInTheDocument();
  });

  it('shows the error and a way to try again', async () => {
    open(
      '/picking',
      apiOf(['operator'], {
        ...withSession(['operator']),
        'GET /api/pick-lists': () => problem(403, 'identity.forbidden'),
      }),
    );

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'No se han podido cargar las listas.',
    );
    expect(screen.getByRole('button', { name: 'Reintentar' })).toBeInTheDocument();
  });

  it('uses the touch density while it is on screen and gives the old one back', async () => {
    document.documentElement.dataset.density = 'compact';
    const { router } = open(
      '/picking',
      apiOf(['operator'], {
        ...withSession(['operator']),
        'GET /api/pick-lists': () => json([]),
        'GET /api/warehouses': () => json([]),
      }),
    );
    await screen.findByRole('heading', { name: 'Picking' });
    expect(currentDensity()).toBe('touch');

    await router.navigate('/');
    await screen.findByText('Bienvenido a Marea Moda.');

    expect(currentDensity()).toBe('compact');
  });

  it('has its own frame: the way back to the dashboard and no side navigation', async () => {
    open(
      '/picking',
      apiOf(['operator'], { ...withSession(['operator']), 'GET /api/pick-lists': () => json([]) }),
    );

    expect(await screen.findByRole('link', { name: 'Ir al panel' })).toHaveAttribute('href', '/');
    expect(
      screen.queryByRole('navigation', { name: 'Navegación principal' }),
    ).not.toBeInTheDocument();
  });
});
