import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createMemoryRouter } from 'react-router';
import { describe, expect, it } from 'vitest';
import { App } from './App';
import { AuthClient } from './auth/AuthClient';
import { routes } from './routes';
import { apiOf, noSession, open, signIn, withSession } from './testing/renderApp';

describe('routes and authentication', () => {
  it('says the session is being recovered before it knows whether there is one', async () => {
    open('/', apiOf(['operator'], withSession(['operator'])));

    expect(screen.getByRole('status')).toHaveTextContent('Recuperando la sesión…');
    expect(await screen.findByRole('heading', { name: 'Inicio' })).toBeInTheDocument();
  });

  it('sends someone without a session to sign in', async () => {
    open('/', apiOf(['operator'], noSession));

    expect(await screen.findByRole('heading', { name: 'Iniciar sesión' })).toBeInTheDocument();
  });

  it('signs in and lands where the user was going', async () => {
    const user = userEvent.setup();
    const { router } = open('/audit', apiOf(['supervisor'], noSession));

    await signIn(user);

    expect(
      await screen.findByRole('heading', { name: 'Registro de auditoría' }),
    ).toBeInTheDocument();
    expect(router.state.location.pathname).toBe('/audit');
  });

  it('lands on the home page when the user went straight to sign in', async () => {
    const user = userEvent.setup();
    open('/login', apiOf(['operator'], noSession));

    await signIn(user);

    expect(await screen.findByText('Bienvenido a Marea Moda.')).toBeInTheDocument();
  });

  it('explains a wrong password without saying which part was wrong', async () => {
    const user = userEvent.setup();
    const api = apiOf(['operator'], noSession);
    open('/login', api);
    await user.type(await screen.findByLabelText('Correo electrónico'), 'ana@marea-moda.example');
    await user.type(screen.getByLabelText('Contraseña'), 'wrong');

    await user.click(screen.getByRole('button', { name: 'Entrar' }));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'El correo o la contraseña no son correctos.',
    );
    expect(screen.getByRole('button', { name: 'Entrar' })).toBeEnabled();
  });

  it('does not follow a destination that leaves the app', async () => {
    const user = userEvent.setup();
    const client = new AuthClient(apiOf(['operator'], noSession).send);
    const router = createMemoryRouter(routes, {
      initialEntries: [{ pathname: '/login', state: { from: '//evil.example' } }],
    });
    render(<App client={client} router={router} />);

    await signIn(user);

    expect(await screen.findByText('Bienvenido a Marea Moda.')).toBeInTheDocument();
    expect(router.state.location.pathname).toBe('/');
  });

  it('shows the audit log to a supervisor and links to it', async () => {
    open('/audit', apiOf(['supervisor'], withSession(['supervisor'])));

    expect(
      await screen.findByRole('heading', { name: 'Registro de auditoría' }),
    ).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Auditoría' })).toBeInTheDocument();
  });

  it('keeps an operator out of the audit log, and out of the menu', async () => {
    open('/audit', apiOf(['operator'], withSession(['operator'])));

    expect(await screen.findByRole('heading', { name: 'Sin permiso' })).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Auditoría' })).not.toBeInTheDocument();
    expect(
      screen.queryByRole('heading', { name: 'Registro de auditoría' }),
    ).not.toBeInTheDocument();
  });

  it('says who is signed in, with their highest role', async () => {
    open('/', apiOf(['operator', 'admin'], withSession(['operator', 'admin'])));

    const account = await screen.findByRole('group', {
      name: 'Sesión iniciada como ana@marea-moda.example',
    });

    expect(within(account).getByText('ana@marea-moda.example')).toBeInTheDocument();
    expect(within(account).getByText('Administrador')).toBeInTheDocument();
  });

  it('answers a page that does not exist', async () => {
    open('/nowhere', apiOf(['operator'], withSession(['operator'])));

    expect(
      await screen.findByRole('heading', { name: 'Página no encontrada' }),
    ).toBeInTheDocument();
  });

  it('does not tell someone without a session which pages exist', async () => {
    open('/nowhere', apiOf(['operator'], noSession));

    expect(await screen.findByRole('heading', { name: 'Iniciar sesión' })).toBeInTheDocument();
  });

  it('signs out and does not return to the page the user left', async () => {
    const user = userEvent.setup();
    const api = apiOf(['supervisor'], withSession(['supervisor']));
    const { router } = open('/audit', api);

    await user.click(await screen.findByRole('button', { name: 'Cerrar sesión' }));

    expect(await screen.findByRole('heading', { name: 'Iniciar sesión' })).toBeInTheDocument();
    expect(router.state.location.state).toBeNull();
    expect(api.calls).toContain('POST /api/auth/logout');
  });

  it('has a way past the menu for the keyboard', async () => {
    open('/', apiOf(['operator'], withSession(['operator'])));

    expect(await screen.findByRole('link', { name: 'Saltar al contenido' })).toHaveAttribute(
      'href',
      '#content',
    );
  });

  it('says what the system is for, and nothing it does not do', async () => {
    open('/login', apiOf(['operator'], noSession));

    expect(await screen.findByText(/El almacén bajo control/)).toBeInTheDocument();
    expect(screen.getByText(/Picking y etiquetas SSCC/)).toBeInTheDocument();
    expect(screen.queryByText(/SSO|RFID|SAP/)).not.toBeInTheDocument();
  });

  it('shows the password when asked, and hides it again', async () => {
    const user = userEvent.setup();
    open('/login', apiOf(['operator'], noSession));
    const password = await screen.findByLabelText('Contraseña');

    expect(password).toHaveAttribute('type', 'password');

    await user.click(screen.getByRole('button', { name: 'Mostrar la contraseña' }));

    expect(password).toHaveAttribute('type', 'text');

    await user.click(screen.getByRole('button', { name: 'Ocultar la contraseña' }));

    expect(password).toHaveAttribute('type', 'password');
  });

  it('shows the design system to anyone, without signing in', async () => {
    const user = userEvent.setup();
    open('/design', apiOf(['operator'], noSession));

    expect(
      await screen.findByRole('heading', { level: 1, name: 'Sistema de diseño' }),
    ).toBeInTheDocument();
    expect(screen.getByRole('table', { name: 'Existencias de ejemplo' })).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: /Agotado/ }));

    expect(screen.getByRole('cell', { name: 'MM-3310' })).toBeInTheDocument();
    expect(screen.queryByRole('cell', { name: 'MM-1001' })).not.toBeInTheDocument();
  });
});
