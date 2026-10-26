import { render, screen } from '@testing-library/react';
import type userEvent from '@testing-library/user-event';
import { createMemoryRouter } from 'react-router';
import { App } from '../App';
import { AuthClient } from '../auth/AuthClient';
import type { Role } from '../auth/roles';
import { routes } from '../routes';
import { fakeApi, json, me, problem, type Handler } from './fakeApi';

/**
 * What the tests of the screens share: an API that knows one user, and the whole app on a route.
 */
/**
 * The API of a user who is signed in or can sign in with `secret`, with these roles.
 */
export function apiOf(roles: readonly Role[], extra: Record<string, Handler> = {}) {
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

export function open(path: string, api: ReturnType<typeof fakeApi>) {
  const client = new AuthClient(api.send);
  const router = createMemoryRouter(routes, { initialEntries: [path] });
  render(<App client={client} router={router} />);

  return { client, router };
}

export const noSession: Record<string, Handler> = {
  'POST /api/auth/refresh': () => problem(401, 'identity.refresh_token_invalid'),
};

export const withSession = (roles: readonly Role[]): Record<string, Handler> => ({
  'POST /api/auth/refresh': () => json({ token: 'token-0' }),
  'GET /api/me': () => json(me(roles)),
});

export async function signIn(user: ReturnType<typeof userEvent.setup>) {
  await user.type(await screen.findByLabelText('Correo electrónico'), 'ana@marea-moda.example');
  await user.type(screen.getByLabelText('Contraseña'), 'secret');
  await user.click(screen.getByRole('button', { name: 'Entrar' }));
}
