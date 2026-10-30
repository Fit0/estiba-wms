import { http, HttpResponse } from 'msw';
import { setupServer } from 'msw/node';
import type { Handler } from './fakeApi';
import { problem } from './fakeApi';

/**
 * The network of the tests (ADR 0037). Mock Service Worker intercepts the real `fetch`, so a screen
 * runs its requests exactly as in the browser: the client builds the `Request`, serialises the query,
 * sets the headers and reads the answer. `test-setup.ts` starts the server and clears the handlers
 * after each test; a request that no handler answers is an error, not a silent 404. A table is installed when the
 * app is opened, so one table can be shared by several tests.
 */
export const server = setupServer(
  // The lowest priority: a route nobody mocked is the 404 of an unknown route, as on the real server.
  http.all(`${globalThis.location.origin}/*`, () => problem(404, 'shared.not_found')),
);

export { http, HttpResponse };

const origin = (): string => globalThis.location.origin;

/** What a test can ask about the requests an API received. */
export interface MockApi {
  /** The `fetch` the app is given: it goes to the real one, which MSW intercepts. */
  readonly send: typeof fetch;
  /** Starts answering for this test. `open` does it; an API built once and shared by tests is fine. */
  readonly install: () => void;
  /** The `METHOD /path?query` of every request, in order. */
  readonly calls: string[];
  /** The headers of every request, in order. */
  readonly headers: Headers[];
}

function sortedQuery(search: URLSearchParams): string {
  return [...search.entries()]
    .map(([key, value]) => `${key}=${value}`)
    .sort()
    .join('&');
}

/**
 * Answers the requests of the app from a table keyed `METHOD /path?query`. The query is compared
 * without regard to the order of its parameters, as a server does. A request with no entry goes to
 * the next handler and, at the end, to the 404 of an unknown route. A test that needs more (a delay,
 * a network error, an answer that depends on the query) adds `server.use(http.get(...))`: placed
 * after `open` it wins over the table, placed before it answers the routes the table lacks.
 */
export function mockApi(table: Record<string, Handler>): MockApi {
  const calls: string[] = [];
  const headers: Headers[] = [];
  const entries = new Map<string, Handler>();
  for (const [key, handler] of Object.entries(table)) {
    const space = key.indexOf(' ');
    const url = new URL(key.slice(space + 1), 'http://localhost');
    entries.set(`${key.slice(0, space)} ${url.pathname}?${sortedQuery(url.searchParams)}`, handler);
  }

  const install = (): void => {
    server.use(
      http.all(`${origin()}/*`, async ({ request }) => {
        const url = new URL(request.url);
        calls.push(`${request.method} ${url.pathname}${url.search}`);
        headers.push(new Headers(request.headers));

        const handler = entries.get(
          `${request.method} ${url.pathname}?${sortedQuery(url.searchParams)}`,
        );
        if (handler === undefined) {
          // Not in the table: the next handler answers (one from `server.use`, or the 404 at the end).
          return undefined;
        }
        const text = await request.clone().text();
        const body: unknown = text === '' ? undefined : JSON.parse(text);

        return handler({ headers: new Headers(request.headers), body });
      }),
    );
  };

  const send: typeof fetch = (input, init) => {
    if (typeof input !== 'string') {
      throw new TypeError('The session only sends paths.');
    }

    return fetch(new URL(input, origin()), init);
  };

  return { send, install, calls, headers };
}
