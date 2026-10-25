import type { Role } from '../auth/roles';

/**
 * A stand-in for the API in tests: it answers by `METHOD /path` and remembers what was asked.
 * Until the tests use MSW (E06), this keeps them free of a network and of the real server.
 */
export type Handler = (request: {
  headers: Headers;
  body: unknown;
}) => Response | Promise<Response>;

export interface FakeApi {
  readonly send: typeof fetch;
  /** The `METHOD /path` of every request, in order. */
  readonly calls: string[];
  /** The headers of every request, in order. */
  readonly headers: Headers[];
}

export function json(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

export function problem(status: number, code: string): Response {
  return new Response(JSON.stringify({ status, code, detail: code, parameters: {} }), {
    status,
    headers: { 'Content-Type': 'application/problem+json' },
  });
}

export function me(roles: readonly Role[], email = 'ana@marea-moda.example') {
  return {
    id: '0192f2b4-7c3e-7a15-9d5b-3c1f0a8e6b2d',
    email,
    roles,
    tenant: {
      id: '0192f2b4-7c3e-7a15-9d5b-3c1f0a8e6b2e',
      name: 'Marea Moda',
      defaultLanguage: 'es',
    },
  };
}

export function fakeApi(handlers: Record<string, Handler>): FakeApi {
  const calls: string[] = [];
  const headers: Headers[] = [];

  const send: typeof fetch = async (input, init) => {
    if (typeof input !== 'string') {
      throw new TypeError('The fake API only takes paths.');
    }
    const key = `${init?.method ?? 'GET'} ${input}`;
    const sent = new Headers(init?.headers);
    calls.push(key);
    headers.push(sent);

    const handler = handlers[key];
    if (handler === undefined) {
      return problem(404, 'shared.not_found');
    }
    const body: unknown = typeof init?.body === 'string' ? JSON.parse(init.body) : undefined;

    return handler({ headers: sent, body });
  };

  return { send, calls, headers };
}
