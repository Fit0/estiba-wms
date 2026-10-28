import { describe, expect, it } from 'vitest';
import { AuthClient } from '../auth/AuthClient';
import { fakeApi, json, me, problem } from '../testing/fakeApi';
import { createApiClient } from './client';

async function signedIn(extra: Parameters<typeof fakeApi>[0]) {
  const api = fakeApi({
    'POST /api/auth/login': () => json({ token: 'token-1' }),
    'GET /api/me': () => json(me(['operator'])),
    ...extra,
  });
  const session = new AuthClient(api.send);
  await session.signIn('ana@marea-moda.example', 'secret');

  return { api, session };
}

describe('the typed client', () => {
  it('sends the token, the language and the filters of the query', async () => {
    const { api, session } = await signedIn({
      'GET /api/stock-items?status=available&page=2': () => json([]),
    });

    const result = await createApiClient(session, () => 'en').GET('/api/stock-items', {
      params: { query: { status: 'available', page: 2 } },
    });

    expect(result.data).toEqual([]);
    expect(api.calls.at(-1)).toBe('GET /api/stock-items?status=available&page=2');
    expect(api.headers.at(-1)?.get('Authorization')).toBe('Bearer token-1');
    expect(api.headers.at(-1)?.get('Accept-Language')).toBe('en');
  });

  it('fills the path parameters', async () => {
    const { api, session } = await signedIn({
      'GET /api/stock-items/abc': () => json({ id: 'abc' }),
    });

    await createApiClient(session, () => 'es').GET('/api/stock-items/{id}', {
      params: { path: { id: 'abc' } },
    });

    expect(api.calls.at(-1)).toBe('GET /api/stock-items/abc');
  });

  it('renews the session once when the token has expired, and asks again', async () => {
    let attempts = 0;
    const { api, session } = await signedIn({
      'POST /api/auth/refresh': () => json({ token: 'token-2' }),
      'GET /api/stock-items': ({ headers }) => {
        attempts += 1;

        return headers.get('Authorization') === 'Bearer token-2'
          ? json([{ id: 'a' }])
          : problem(401, 'identity.token_expired');
      },
    });

    const result = await createApiClient(session, () => 'es').GET('/api/stock-items');

    expect(result.data).toEqual([{ id: 'a' }]);
    expect(attempts).toBe(2);
    expect(api.calls).toContain('POST /api/auth/refresh');
  });

  it('sends the body of a post as it is', async () => {
    let received: unknown;
    const { session } = await signedIn({
      'POST /api/warehouses': ({ body }) => {
        received = body;

        return json({ id: 'w' }, 201);
      },
    });

    await createApiClient(session, () => 'es').POST('/api/warehouses', {
      body: { code: 'ZGZ', name: 'Zaragoza' },
    });

    expect(received).toEqual({ code: 'ZGZ', name: 'Zaragoza' });
  });

  it('returns the problem of a refusal for the caller to read', async () => {
    const { session } = await signedIn({
      'GET /api/stock-items': () => problem(403, 'shared.forbidden'),
    });

    const result = await createApiClient(session, () => 'es').GET('/api/stock-items');

    expect(result.response.status).toBe(403);
    expect(result.error).toMatchObject({ code: 'shared.forbidden' });
  });
});
