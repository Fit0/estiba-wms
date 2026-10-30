import { describe, expect, it } from 'vitest';
import { json } from './fakeApi';
import { http, HttpResponse, mockApi, server } from './msw';

const get = (path: string) => fetch(new URL(path, globalThis.location.origin));

describe('the network of the tests', () => {
  it('matches the query without regard to the order of its parameters', async () => {
    const api = mockApi({ 'GET /api/things?page=1&status=open': () => json(['found']) });
    api.install();

    const answer = await get('/api/things?status=open&page=1');

    expect(await answer.json()).toEqual(['found']);
    expect(api.calls).toEqual(['GET /api/things?status=open&page=1']);
  });

  it('answers an unknown route with the 404 of the server', async () => {
    const api = mockApi({});
    api.install();

    const answer = await get('/api/nothing');

    expect(answer.status).toBe(404);
    expect(await answer.json()).toMatchObject({ code: 'shared.not_found' });
  });

  it('hands the body and the headers of a request to its handler', async () => {
    let seen: unknown;
    const api = mockApi({
      'POST /api/things': ({ body, headers }) => {
        seen = { body, type: headers.get('Content-Type') };

        return json({}, 201);
      },
    });
    api.install();

    await fetch(new URL('/api/things', globalThis.location.origin), {
      method: 'POST',
      headers: { 'Content-Type': 'application/merge-patch+json' },
      body: JSON.stringify({ name: 'x' }),
    });

    expect(seen).toEqual({ body: { name: 'x' }, type: 'application/merge-patch+json' });
  });

  it('lets a handler of the test answer the routes the table lacks, and win when it comes later', async () => {
    server.use(http.get('*/api/extra', () => HttpResponse.json({ from: 'test' })));
    const api = mockApi({ 'GET /api/table': () => json({ from: 'table' }) });
    api.install();
    server.use(http.get('*/api/table', () => HttpResponse.json({ from: 'override' })));

    expect(await (await get('/api/extra')).json()).toEqual({ from: 'test' });
    expect(await (await get('/api/table')).json()).toEqual({ from: 'override' });
  });

  it('starts every test without the handlers of the one before', async () => {
    expect((await get('/api/extra')).status).toBe(404);
  });
});
