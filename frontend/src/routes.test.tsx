import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createMemoryRouter } from 'react-router';
import { describe, expect, it } from 'vitest';
import { App } from './App';
import { AuthClient } from './auth/AuthClient';
import type { Role } from './auth/roles';
import { fakeApi, json, me, problem, type Handler } from './testing/fakeApi';
import { routes } from './routes';

/**
 * The API of a user who is signed in or can sign in with `secret`, with these roles.
 */
function apiOf(roles: readonly Role[], extra: Record<string, Handler> = {}) {
  return fakeApi({
    'POST /api/auth/login': ({ body }) =>
      body !== null && typeof body === 'object' && 'password' in body && body.password === 'secret'
        ? json({ token: 'token-1' })
        : problem(401, 'identity.credentials_invalid'),
    'GET /api/me': () => json(me(roles)),
    'POST /api/auth/logout': () => new Response(null, { status: 204 }),
    ...extra,
  });
}

function open(path: string, api: ReturnType<typeof fakeApi>) {
  const client = new AuthClient(api.send);
  const router = createMemoryRouter(routes, { initialEntries: [path] });
  render(<App client={client} router={router} />);

  return { client, router };
}

const noSession: Record<string, Handler> = {
  'POST /api/auth/refresh': () => problem(401, 'identity.refresh_token_invalid'),
};

const withSession = (roles: readonly Role[]): Record<string, Handler> => ({
  'POST /api/auth/refresh': () => json({ token: 'token-0' }),
  'GET /api/me': () => json(me(roles)),
});

async function signIn(user: ReturnType<typeof userEvent.setup>) {
  await user.type(await screen.findByLabelText('Correo electrónico'), 'ana@marea-moda.example');
  await user.type(screen.getByLabelText('Contraseña'), 'secret');
  await user.click(screen.getByRole('button', { name: 'Entrar' }));
}

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

    expect(
      await screen.findByText(/Sesión iniciada como ana@marea-moda.example \(Administrador\)/),
    ).toBeInTheDocument();
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
});
