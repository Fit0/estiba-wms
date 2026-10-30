# 0037. Mock Service Worker for the tests of the screens

- Status: Accepted
- Date: 2026-10-30

## Context

The tests of the screens used `fakeApi`, a function that stood in for `fetch` and was given to the session (ADR 0025 announced MSW for E06-05). It worked, but it tested less than it seemed: the typed client built its `Request`, the session took it apart into a path and options, and the fake answered a string key. Anything the browser does between the client and the network (serialising the query, the headers of the real `Request`, a dropped connection) was out of reach, and a key like `GET /api/orders?page=1&itemsPerPage=30` broke if the parameters changed order.

## Decisions

- **Mock Service Worker 3 in Node.** `testing/msw.ts` creates the server; `test-setup.ts` starts it, clears the handlers after each test and closes it at the end. The app now uses the real `fetch` of Node against the origin of jsdom, and MSW intercepts it.
- **`apiOf` and `open` keep their shape.** The table of handlers keyed `METHOD /path?query` is now installed on the MSW server when the app is opened, so the 300 tests that use it moved without changes, and a table can still be shared by several tests. The query is compared without regard to the order of its parameters.
- **Native handlers for what a table cannot say.** A test that needs a delay, a network error, an answer that depends on the query or a different answer on each call writes `server.use(http.get('*/api/...', ...))`. Placed before `open`, it answers the routes the table lacks; placed after, it wins over the table. The tests of the catalogue show both.
- **A route nobody mocked is a 404**, like the real server's. A request to another origin is an error (`onUnhandledFrame: 'error'`).
- **`fakeApi` stays for the unit tests** of `AuthClient`, the typed client and the stock hooks, which test the injection of `fetch` itself and need no network.

## Consequences

- MSW needs Node 22.12 or later; the project asks for 24. After the dependency changes the frontend container needs `npm ci`.
- A dropped connection is retried twice by TanStack Query (ADR 0025), so a test of a failing request waits for the backoff or uses a 4xx.
- The same handlers could feed Playwright (E06-06) or a demo mode of the app in the browser (`msw/browser`); that is not done here.
