import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { json, problem } from '../testing/fakeApi';
import { apiOf, open, withSession } from '../testing/renderApp';

const zone = {
  id: 'z-a',
  warehouseId: 'w-1',
  code: 'A',
  name: 'Moda mujer',
  storageClass: 'chilled',
  pickSequence: 10,
  families: [],
};
const warehouse = { id: 'w-1', code: 'ZGZ1', name: 'Zaragoza', zones: [zone] };

const location = (code: string, status: 'active' | 'blocked' = 'active') => ({
  id: `l-${code}`,
  warehouseId: 'w-1',
  zoneId: 'z-a',
  code,
  type: 'pick_face',
  pickSequence: 10,
  status,
});

function paged(items: unknown[], total = items.length): Response {
  return new Response(JSON.stringify(items), {
    status: 200,
    headers: { 'Content-Type': 'application/json', 'X-Total-Count': String(total) },
  });
}

describe('one warehouse', () => {
  it('shows its zones and its locations, with how many there are in all', async () => {
    open(
      '/warehouses/w-1',
      apiOf(['operator'], {
        ...withSession(['operator']),
        'GET /api/warehouses': () => json([warehouse]),
        'GET /api/warehouses/w-1': () => json(warehouse),
        'GET /api/warehouses/w-1/locations?page=1&itemsPerPage=30': () =>
          paged([location('A-01-01'), location('A-01-02', 'blocked')], 42),
      }),
    );

    expect(await screen.findByRole('heading', { name: 'ZGZ1 · Zaragoza' })).toBeInTheDocument();
    const zones = await screen.findByRole('region', { name: 'Zonas del almacén' });
    expect(within(zones).getByText('Moda mujer')).toBeInTheDocument();
    expect(within(zones).getByText('Refrigerado')).toBeInTheDocument();

    const locations = await screen.findByRole('region', { name: 'Ubicaciones del almacén' });
    expect(await within(locations).findByText('A-01-02')).toBeInTheDocument();
    expect(within(locations).getByText('Bloqueada')).toBeInTheDocument();
    expect(within(locations).getAllByText('Frente de picking')).toHaveLength(2);
    expect(screen.queryByRole('button', { name: 'Nueva zona' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /^Bloquear/ })).not.toBeInTheDocument();
  });

  it('asks for the status the person filters by', async () => {
    const user = userEvent.setup();
    const api = apiOf(['operator'], {
      ...withSession(['operator']),
      'GET /api/warehouses': () => json([warehouse]),
      'GET /api/warehouses/w-1': () => json(warehouse),
      'GET /api/warehouses/w-1/locations?page=1&itemsPerPage=30': () =>
        paged([location('A-01-01')]),
      'GET /api/warehouses/w-1/locations?page=1&itemsPerPage=30&status=blocked': () =>
        paged([location('B-02-01', 'blocked')]),
    });
    open('/warehouses/w-1', api);

    await screen.findByText('A-01-01');
    await user.click(screen.getByRole('combobox', { name: 'Estado' }));
    await user.click(await screen.findByRole('option', { name: 'Bloqueada' }));

    expect(await screen.findByText('B-02-01')).toBeInTheDocument();
  });

  it('lets a supervisor block a location with a reason, and unblock another', async () => {
    const user = userEvent.setup();
    const sent: unknown[] = [];
    const api = apiOf(['supervisor'], {
      ...withSession(['supervisor']),
      'GET /api/warehouses': () => json([warehouse]),
      'GET /api/warehouses/w-1': () => json(warehouse),
      'GET /api/warehouses/w-1/locations?page=1&itemsPerPage=30': () =>
        paged([location('A-01-01'), location('A-01-02', 'blocked')]),
      'POST /api/locations/l-A-01-01/block': ({ body }) => {
        sent.push(body);

        return json(location('A-01-01', 'blocked'));
      },
      'POST /api/locations/l-A-01-02/unblock': () => json(location('A-01-02')),
    });
    open('/warehouses/w-1', api);

    await user.click(await screen.findByRole('button', { name: 'Bloquear A-01-01' }));
    const dialog = await screen.findByRole('dialog', { name: 'Bloquear la ubicación A-01-01' });

    await user.click(within(dialog).getByRole('button', { name: 'Bloquear' }));
    expect(await within(dialog).findByText('Escribe un valor.')).toBeInTheDocument();
    expect(sent).toHaveLength(0);

    await user.type(within(dialog).getByLabelText('Motivo'), 'Estantería dañada');
    await user.click(within(dialog).getByRole('button', { name: 'Bloquear' }));
    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(sent).toEqual([{ reason: 'Estantería dañada' }]);

    await user.click(screen.getByRole('button', { name: 'Desbloquear A-01-02' }));
    await waitFor(() => {
      expect(api.calls).toContain('POST /api/locations/l-A-01-02/unblock');
    });
  });

  it('defines a zone and then a location in it', async () => {
    const user = userEvent.setup();
    const sent: Record<string, unknown> = {};
    const api = apiOf(['supervisor'], {
      ...withSession(['supervisor']),
      'GET /api/warehouses': () => json([warehouse]),
      'GET /api/warehouses/w-1': () => json(warehouse),
      'GET /api/warehouses/w-1/locations?page=1&itemsPerPage=30': () => paged([]),
      'POST /api/warehouses/w-1/zones': ({ body }) => {
        sent.zone = body;

        return json({ ...zone, code: 'B', name: 'Hogar' }, 201);
      },
      'POST /api/warehouses/w-1/locations': ({ body }) => {
        sent.location = body;

        return json(location('B-03-02'), 201);
      },
    });
    open('/warehouses/w-1', api);

    await user.click(await screen.findByRole('button', { name: 'Nueva zona' }));
    const zoneDialog = await screen.findByRole('dialog', { name: 'Nueva zona' });
    await user.type(within(zoneDialog).getByLabelText('Código'), 'b');
    await user.type(within(zoneDialog).getByLabelText('Nombre'), 'Hogar');
    await user.clear(within(zoneDialog).getByLabelText('Orden de picking'));
    await user.type(within(zoneDialog).getByLabelText('Orden de picking'), '20');
    await user.click(within(zoneDialog).getByRole('button', { name: 'Guardar' }));
    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(sent.zone).toEqual({
      code: 'B',
      name: 'Hogar',
      storageClass: 'ambient',
      pickSequence: 20,
    });

    await user.click(await screen.findByRole('button', { name: 'Nueva ubicación' }));
    const dialog = await screen.findByRole('dialog', { name: 'Nueva ubicación' });
    await user.type(within(dialog).getByLabelText('Código'), 'b-3');
    await user.type(within(dialog).getByLabelText('Peso máximo (kg)'), '0');
    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));
    expect(await within(dialog).findByText(/Pasillo-módulo-nivel/)).toBeInTheDocument();
    expect(await within(dialog).findByText(/Déjalo vacío/)).toBeInTheDocument();

    await user.clear(within(dialog).getByLabelText('Código'));
    await user.type(within(dialog).getByLabelText('Código'), 'b-03-02');
    await user.clear(within(dialog).getByLabelText('Peso máximo (kg)'));
    await user.type(within(dialog).getByLabelText('Peso máximo (kg)'), '500');
    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));
    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(sent.location).toEqual({
      zoneId: 'z-a',
      code: 'B-03-02',
      type: 'reserve',
      pickSequence: 10,
      maxWeightKg: 500,
      maxVolumeLitres: null,
      maxPalletPositions: null,
    });
  });

  it('says so when the warehouse does not exist', async () => {
    open(
      '/warehouses/nope',
      apiOf(['operator'], {
        ...withSession(['operator']),
        'GET /api/warehouses': () => json([warehouse]),
        'GET /api/warehouses/nope': () => problem(404, 'shared.not_found'),
      }),
    );

    expect(
      await screen.findByRole('heading', { name: 'No se ha encontrado el almacén.' }),
    ).toBeInTheDocument();
  });
});
