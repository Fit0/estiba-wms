import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it } from 'vitest';
import { json, problem } from '../testing/fakeApi';
import { apiOf, open, withSession } from '../testing/renderApp';

const zoneA = {
  id: 'z-a',
  warehouseId: 'w-1',
  code: 'A',
  name: 'Moda mujer',
  storageClass: 'ambient',
  pickSequence: 10,
  families: [],
};
const zgz = { id: 'w-1', code: 'ZGZ1', name: 'Zaragoza', zones: [zoneA] };
const bcn = { id: 'w-2', code: 'BCN1', name: 'Barcelona', zones: [] };

afterEach(() => {
  localStorage.clear();
});

describe('the warehouses', () => {
  it('lists them, and the top bar offers them to work in', async () => {
    const user = userEvent.setup();
    open(
      '/warehouses',
      apiOf(['operator'], {
        ...withSession(['operator']),
        'GET /api/warehouses': () => json([zgz, bcn]),
      }),
    );

    const table = await screen.findByRole('region', { name: 'Almacenes' });
    expect(await within(table).findByRole('link', { name: 'Abrir Zaragoza' })).toHaveAttribute(
      'href',
      '/warehouses/w-1',
    );
    expect(screen.queryByRole('button', { name: 'Nuevo almacén' })).not.toBeInTheDocument();

    await user.click(await screen.findByRole('combobox', { name: 'Almacén' }));
    await user.click(await screen.findByRole('option', { name: 'BCN1 · Barcelona' }));

    expect(localStorage.getItem('estiba-warehouse')).toBe('w-2');
  });

  it('checks the form, shows what the API says if it refuses, and saves once it accepts', async () => {
    const user = userEvent.setup();
    let taken = true;
    const sent: unknown[] = [];
    const api = apiOf(['supervisor'], {
      ...withSession(['supervisor']),
      'GET /api/warehouses': () => json([zgz]),
      'POST /api/warehouses': ({ body }) => {
        sent.push(body);

        return taken
          ? new Response(
              JSON.stringify({
                status: 409,
                code: 'warehouse.code_taken',
                detail: 'Ese código ya existe.',
                parameters: {},
              }),
              { status: 409, headers: { 'Content-Type': 'application/problem+json' } },
            )
          : json({ id: 'w-3', code: 'ZGZ-1', name: 'Zaragoza', zones: [] }, 201);
      },
    });
    open('/warehouses', api);

    await user.click(await screen.findByRole('button', { name: 'Nuevo almacén' }));
    const dialog = await screen.findByRole('dialog', { name: 'Nuevo almacén' });

    await user.type(within(dialog).getByLabelText('Código'), 'z');
    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));
    expect(await within(dialog).findByText(/De 2 a 12 letras o cifras/)).toBeInTheDocument();
    expect(await within(dialog).findByText('Escribe un valor.')).toBeInTheDocument();
    expect(sent).toHaveLength(0);

    await user.clear(within(dialog).getByLabelText('Código'));
    await user.type(within(dialog).getByLabelText('Código'), 'zgz-1');
    await user.type(within(dialog).getByLabelText('Nombre'), 'Zaragoza');
    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));

    expect(await within(dialog).findByRole('alert')).toHaveTextContent(
      'No se ha podido guardar: Ese código ya existe.',
    );
    expect(sent).toEqual([{ code: 'ZGZ-1', name: 'Zaragoza' }]);

    taken = false;
    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(api.calls.filter((call) => call === 'GET /api/warehouses').length).toBeGreaterThan(1);
  });

  it('says so when the warehouses cannot be loaded', async () => {
    open(
      '/warehouses',
      apiOf(['operator'], {
        ...withSession(['operator']),
        'GET /api/warehouses': () => problem(500, 'shared.internal_error'),
      }),
    );

    expect(await screen.findByText('No se han podido cargar los datos.')).toBeInTheDocument();
  });
});
