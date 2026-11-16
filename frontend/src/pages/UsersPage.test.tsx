import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it } from 'vitest';
import { json, problem } from '../testing/fakeApi';
import { paged } from '../testing/paged';
import { apiOf, open, withSession } from '../testing/renderApp';

const ownId = '0192f2b4-7c3e-7a15-9d5b-3c1f0a8e6b2d';
const ana = { id: ownId, email: 'ana@marea-moda.example', roles: ['admin'], status: 'active' };
const luis = { id: 'u-2', email: 'luis@marea-moda.example', roles: ['operator'], status: 'active' };
const eva = {
  id: 'u-3',
  email: 'eva@marea-moda.example',
  roles: ['supervisor'],
  status: 'disabled',
};

afterEach(() => {
  localStorage.clear();
});

describe('the users', () => {
  it('lists them, and offers no way to disable yourself', async () => {
    open(
      '/users',
      apiOf(['admin'], {
        ...withSession(['admin']),
        'GET /api/users?page=1&itemsPerPage=25': () => paged([ana, luis, eva]),
      }),
    );

    const table = await screen.findByRole('region', { name: 'Usuarios' });
    expect(await within(table).findByText('luis@marea-moda.example')).toBeInTheDocument();
    expect(within(table).getByText('Operario')).toBeInTheDocument();
    expect(within(table).getByText('Deshabilitado')).toBeInTheDocument();
    expect(
      within(table).getByRole('button', { name: 'Deshabilitar a luis@marea-moda.example' }),
    ).toBeInTheDocument();
    expect(
      within(table).getByRole('button', { name: 'Habilitar a eva@marea-moda.example' }),
    ).toBeInTheDocument();
    expect(
      within(table).queryByRole('button', { name: 'Deshabilitar a ana@marea-moda.example' }),
    ).not.toBeInTheDocument();
  });

  it('checks the form, shows what the API says if it refuses, and saves once it accepts', async () => {
    const user = userEvent.setup();
    let taken = true;
    const sent: unknown[] = [];
    const api = apiOf(['admin'], {
      ...withSession(['admin']),
      'GET /api/users?page=1&itemsPerPage=25': () => paged([ana]),
      'POST /api/users': ({ body }) => {
        sent.push(body);

        return taken
          ? problem(409, 'identity.email_already_used')
          : json({ ...luis, roles: ['operator'] }, 201);
      },
    });
    open('/users', api);

    await user.click(await screen.findByRole('button', { name: 'Nuevo usuario' }));
    const dialog = await screen.findByRole('dialog', { name: 'Nuevo usuario' });

    await user.type(within(dialog).getByLabelText('Correo electrónico'), 'luis');
    await user.type(within(dialog).getByLabelText('Contraseña inicial'), 'corta');
    await user.click(within(dialog).getByRole('checkbox', { name: 'Operario' }));
    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));
    expect(await within(dialog).findByText('Escribe un correo válido.')).toBeInTheDocument();
    expect(
      within(dialog).getByText('La contraseña debe tener entre 12 y 72 caracteres.'),
    ).toBeInTheDocument();
    expect(within(dialog).getByText('Elige al menos un rol.')).toBeInTheDocument();
    expect(sent).toHaveLength(0);

    await user.clear(within(dialog).getByLabelText('Correo electrónico'));
    await user.type(within(dialog).getByLabelText('Correo electrónico'), 'luis@marea-moda.example');
    await user.clear(within(dialog).getByLabelText('Contraseña inicial'));
    await user.type(within(dialog).getByLabelText('Contraseña inicial'), 'una contraseña larga');
    await user.click(within(dialog).getByRole('checkbox', { name: 'Operario' }));
    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));

    expect(await within(dialog).findByRole('alert')).toBeInTheDocument();
    expect(sent).toEqual([
      {
        email: 'luis@marea-moda.example',
        password: 'una contraseña larga',
        roles: ['operator'],
      },
    ]);

    taken = false;
    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(api.calls.filter((call) => call.startsWith('GET /api/users')).length).toBeGreaterThan(1);
  });

  it('replaces the roles of a user', async () => {
    const user = userEvent.setup();
    const sent: unknown[] = [];
    open(
      '/users',
      apiOf(['admin'], {
        ...withSession(['admin']),
        'GET /api/users?page=1&itemsPerPage=25': () => paged([ana, luis]),
        'POST /api/users/u-2/roles': ({ body }) => {
          sent.push(body);

          return json({ ...luis, roles: ['operator', 'supervisor'] });
        },
      }),
    );

    await user.click(
      await screen.findByRole('button', { name: 'Cambiar los roles de luis@marea-moda.example' }),
    );
    const dialog = await screen.findByRole('dialog', { name: 'Roles de luis@marea-moda.example' });
    expect(within(dialog).getByRole('checkbox', { name: 'Operario' })).toBeChecked();

    await user.click(within(dialog).getByRole('checkbox', { name: 'Supervisor' }));
    await user.click(within(dialog).getByRole('button', { name: 'Guardar' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(sent).toEqual([{ roles: ['operator', 'supervisor'] }]);
  });

  it('asks before disabling, and says why the API refused', async () => {
    const user = userEvent.setup();
    let refuse = true;
    const api = apiOf(['admin'], {
      ...withSession(['admin']),
      'GET /api/users?page=1&itemsPerPage=25': () => paged([ana, luis]),
      'POST /api/users/u-2/disable': () =>
        refuse
          ? problem(409, 'identity.user_already_disabled')
          : json({ ...luis, status: 'disabled' }),
    });
    open('/users', api);

    await user.click(
      await screen.findByRole('button', { name: 'Deshabilitar a luis@marea-moda.example' }),
    );
    const dialog = await screen.findByRole('dialog', {
      name: 'Deshabilitar a luis@marea-moda.example',
    });
    await user.click(within(dialog).getByRole('button', { name: 'Deshabilitar' }));
    expect(await within(dialog).findByRole('alert')).toBeInTheDocument();

    refuse = false;
    await user.click(within(dialog).getByRole('button', { name: 'Deshabilitar' }));
    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
    expect(api.calls).toContain('POST /api/users/u-2/disable');
  });

  it('is only for admins', async () => {
    open('/users', apiOf(['supervisor'], withSession(['supervisor'])));

    expect(await screen.findByRole('heading', { name: 'Sin permiso' })).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Usuarios' })).not.toBeInTheDocument();
  });
});
