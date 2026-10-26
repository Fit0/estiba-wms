import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fakeApi, json, me, problem } from '../testing/fakeApi';
import { AuthClient, SignInError } from './AuthClient';

describe('AuthClient', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('starts restoring and stays so until it is told to pick the session up', () => {
    const client = new AuthClient(fakeApi({}).send);

    expect(client.getState()).toEqual({ status: 'restoring' });
  });

  it('signs in with the credentials and loads who the user is', async () => {
    const api = fakeApi({
      'POST /api/auth/login': ({ body }) =>
        body !== null && typeof body === 'object' && 'password' in body && body.password === 'right'
          ? json({ token: 'token-1' })
          : problem(401, 'identity.credentials_invalid'),
      'GET /api/me': ({ headers }) =>
        headers.get('Authorization') === 'Bearer token-1'
          ? json(me(['supervisor']))
          : problem(401, 'shared.unauthenticated'),
    });
    const client = new AuthClient(api.send);

    await client.signIn('ana@marea-moda.example', 'right');

    expect(client.getState()).toMatchObject({
      status: 'signedIn',
      user: { email: 'ana@marea-moda.example', roles: ['supervisor'] },
    });
  });

  it('keeps the token out of the places a script could read it', async () => {
    const api = fakeApi({
      'POST /api/auth/login': () => json({ token: 'token-1' }),
      'GET /api/me': () => json(me(['operator'])),
    });
    const client = new AuthClient(api.send);

    await client.signIn('ana@marea-moda.example', 'right');

    expect(localStorage.length).toBe(0);
    expect(sessionStorage.length).toBe(0);
    expect(document.cookie).toBe('');
  });

  it('says which problem the API found when the credentials are wrong', async () => {
    const client = new AuthClient(
      fakeApi({ 'POST /api/auth/login': () => problem(401, 'identity.credentials_invalid') }).send,
    );

    await expect(client.signIn('ana@marea-moda.example', 'wrong')).rejects.toEqual(
      new SignInError('identity.credentials_invalid'),
    );
    expect(client.getState()).toEqual({ status: 'restoring' });
  });

  it('picks the session up from the cookie after a reload', async () => {
    const api = fakeApi({
      'POST /api/auth/refresh': () => json({ token: 'token-2' }),
      'GET /api/me': ({ headers }) =>
        headers.get('Authorization') === 'Bearer token-2'
          ? json(me(['operator']))
          : problem(401, 'shared.unauthenticated'),
    });
    const client = new AuthClient(api.send);

    await client.restore();

    expect(client.getState().status).toBe('signedIn');
  });

  it('shares one renewal between the callers that ask at once', async () => {
    const api = fakeApi({
      'POST /api/auth/refresh': () => json({ token: 'token-2' }),
      'GET /api/me': () => json(me(['operator'])),
    });
    const client = new AuthClient(api.send);

    await Promise.all([client.restore(), client.restore()]);

    expect(api.calls.filter((call) => call === 'POST /api/auth/refresh')).toHaveLength(1);
  });

  it('ends up signed out when there is no session to pick up', async () => {
    const api = fakeApi({
      'POST /api/auth/refresh': () => problem(401, 'identity.refresh_token_invalid'),
    });
    const client = new AuthClient(api.send);

    const restoring = client.restore();
    await vi.runAllTimersAsync();
    await restoring;

    expect(client.getState()).toEqual({ status: 'signedOut', reason: 'none' });
  });

  // Two tabs open at once send the same cookie: the server refuses the second for a few seconds.
  it('asks again once when the renewal is refused, for the cookie of the other tab', async () => {
    let asked = 0;
    const api = fakeApi({
      'POST /api/auth/refresh': () =>
        ++asked === 1 ? problem(401, 'identity.refresh_token_invalid') : json({ token: 'token-3' }),
      'GET /api/me': () => json(me(['operator'])),
    });
    const client = new AuthClient(api.send);

    const restoring = client.restore();
    await vi.runAllTimersAsync();
    await restoring;

    expect(asked).toBe(2);
    expect(client.getState().status).toBe('signedIn');
  });

  // The sign-in page is shown while the cookie is still being tried, and a person can be quicker.
  it('is not signed out by the end of a search that started before the sign-in', async () => {
    const api = fakeApi({
      'POST /api/auth/refresh': () => problem(401, 'identity.refresh_token_invalid'),
      'POST /api/auth/login': () => json({ token: 'token-1' }),
      'GET /api/me': () => json(me(['operator'])),
    });
    const client = new AuthClient(api.send);

    const restoring = client.restore();
    await client.signIn('ana@marea-moda.example', 'right');
    await vi.runAllTimersAsync();
    await restoring;

    expect(client.getState().status).toBe('signedIn');
  });

  it('renews the session and repeats the request when the token has expired', async () => {
    let token = 'old';
    const api = fakeApi({
      'POST /api/auth/login': () => json({ token }),
      'POST /api/auth/refresh': () => {
        token = 'new';

        return json({ token });
      },
      'GET /api/me': () => json(me(['operator'])),
      'GET /api/warehouses': ({ headers }) =>
        headers.get('Authorization') === 'Bearer new'
          ? json([])
          : problem(401, 'shared.token_expired'),
    });
    const client = new AuthClient(api.send);
    await client.signIn('ana@marea-moda.example', 'right');

    const response = await client.authorizedFetch('/api/warehouses');

    expect(response.status).toBe(200);
    expect(client.getState().status).toBe('signedIn');
  });

  it('signs the user out when the session cannot be renewed', async () => {
    const api = fakeApi({
      'POST /api/auth/login': () => json({ token: 'old' }),
      'POST /api/auth/refresh': () => problem(401, 'identity.refresh_token_invalid'),
      'GET /api/me': () => json(me(['operator'])),
      'GET /api/warehouses': () => problem(401, 'shared.token_expired'),
    });
    const client = new AuthClient(api.send);
    await client.signIn('ana@marea-moda.example', 'right');

    const asking = client.authorizedFetch('/api/warehouses');
    await vi.runAllTimersAsync();
    const response = await asking;

    expect(response.status).toBe(401);
    expect(client.getState()).toEqual({ status: 'signedOut', reason: 'expired' });
  });

  it('does not renew the session for an answer that is not about the token', async () => {
    const api = fakeApi({
      'POST /api/auth/login': () => json({ token: 'old' }),
      'GET /api/me': () => json(me(['operator'])),
      'POST /api/warehouses': () => problem(403, 'shared.forbidden'),
    });
    const client = new AuthClient(api.send);
    await client.signIn('ana@marea-moda.example', 'right');

    const response = await client.authorizedFetch('/api/warehouses', { method: 'POST' });

    expect(response.status).toBe(403);
    expect(api.calls).not.toContain('POST /api/auth/refresh');
  });

  it('tells the API when the user signs out, and forgets the token', async () => {
    const api = fakeApi({
      'POST /api/auth/login': () => json({ token: 'token-1' }),
      'GET /api/me': () => json(me(['operator'])),
      'POST /api/auth/logout': () => new Response(null, { status: 204 }),
      'GET /api/warehouses': ({ headers }) => json({ authorization: headers.get('Authorization') }),
    });
    const client = new AuthClient(api.send);
    await client.signIn('ana@marea-moda.example', 'right');

    await client.signOut();
    const response = await client.authorizedFetch('/api/warehouses');

    expect(client.getState()).toEqual({ status: 'signedOut', reason: 'left' });
    expect(api.calls).toContain('POST /api/auth/logout');
    expect(await response.json()).toEqual({ authorization: null });
  });

  it('is signed out even if the API cannot be reached to sign out', async () => {
    const api = fakeApi({
      'POST /api/auth/login': () => json({ token: 'token-1' }),
      'GET /api/me': () => json(me(['operator'])),
      'POST /api/auth/logout': () => {
        throw new TypeError('Failed to fetch');
      },
    });
    const client = new AuthClient(api.send);
    await client.signIn('ana@marea-moda.example', 'right');

    await client.signOut();

    expect(client.getState()).toEqual({ status: 'signedOut', reason: 'left' });
  });

  it('tells its listeners when the session changes', async () => {
    const api = fakeApi({
      'POST /api/auth/login': () => json({ token: 'token-1' }),
      'GET /api/me': () => json(me(['operator'])),
    });
    const client = new AuthClient(api.send);
    const listener = vi.fn();
    const unsubscribe = client.subscribe(listener);

    await client.signIn('ana@marea-moda.example', 'right');
    unsubscribe();
    await client.signOut();

    expect(listener).toHaveBeenCalledTimes(1);
  });
});
