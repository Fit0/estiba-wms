# 0025. A typed API client and a cache of server data

- Status: Accepted
- Date: 2026-10-27

## Context

The screens of the back office (E05-07, E06) will read and change data in the API. Until now the only calls were the three of the session (`AuthClient`), written by hand. Three things had to be decided:

1. **Where the types of the API come from**, so that a call to a path that does not exist, or with a filter the API does not take, fails to compile.
2. **How a call reaches the API with the session**: the access token, its renewal and the language.
3. **Where server data lives** in the app, so that a list is not copied into the state of every screen.

ADR 0019 decided that the OpenAPI document is the documentation of the API and that no generated copy of it is committed, since it would drift.

## Options considered

1. **Types written by hand** for each resource. Quick for the first screen, and wrong at the first change of the API. CLAUDE.md forbids it.
2. **A generator that produces a client with functions** (Orval, `openapi-generator`). It gives a function and a hook per operation, but also a lot of generated code to review, and its own way to send a request, which would sidestep the renewal of the session.
3. **`openapi-typescript` for the types and `openapi-fetch` for the calls.** The generator writes only types (one file, no runtime). The client is 6 kB, takes a `fetch` of our own, and checks the path, the parameters and the body against those types.
4. **Server data in `useState` and `useEffect`** of each screen. Nothing to learn, and every screen writes its own loading flag, its own error, and the cancelling of a request that is no longer wanted.
5. **TanStack Query** for server data.

## Decision

- **Options 3 and 5.** `npm run api:types` (`make front-api-types`) reads the OpenAPI document of the **running API** (`/api/docs.jsonopenapi`, public) and writes `frontend/src/api/schema.d.ts`. That file is committed because it is what the compiler reads, and it is regenerated whenever the API changes. The OpenAPI document itself is still not committed (ADR 0019). ESLint and Prettier skip the file.
- **The client goes through the session.** `createApiClient` gives `openapi-fetch` a `fetch` that is `AuthClient.authorizedFetch`: the same token in memory, the same renewal once on a 401, and the same sign-out when it cannot renew (ADR 0022). Nothing else in the app sends a token. The client also sends `Accept-Language`, so the `detail` of an error comes in the language of the screen (ADR 0007).
- **Errors become exceptions at the edge of the query.** The client returns `{ data, error }`; TanStack Query needs a function that throws. `unwrap` throws an `ApiError` with the `status`, the stable `code` (what a screen switches on), the translated `detail` and the `parameters`. A body that is not a problem (a proxy page, nothing at all) becomes `shared.internal_error`.
- **One cache, cleared on sign-out.** `ApiProvider` owns the `QueryClient`. A list is fresh for 30 seconds, a 4xx is never retried, and every key is dropped when the session ends, so the next person to sign in on the same browser sees nothing of the previous one. The queries of a resource sit in one file (`stock.ts`) with their keys (`stockKeys`), which is the pattern for the screens that follow. While the next page loads, the previous one stays (`keepPreviousData`), so a table does not flash empty.
- **The session stays outside the cache.** `AuthClient` is still an external store read with `useSyncExternalStore`. It has to work before any query exists, and it is what the queries depend on.
- **`overrides` for `openapi-typescript`.** It declares a peer of TypeScript 5 and the project uses 6. The override in `package.json` points it at the project's TypeScript, and the generated file type-checks.

## Consequences

- A screen that reads stock writes `useStockItems({ status: 'quarantine' })` and gets `data`, `isPending`, `error` (an `ApiError`) and a refetch, typed from the API.
- At the time of writing, every field of a generated type was optional and the lists had no total. Both are solved in [ADR 0026](0026-the-total-of-a-list-in-a-header-and-required-fields.md): the document marks the fields an answer always has as required, and the paged lists send `X-Total-Count`, which `unwrapPage` reads.
- The types can drift from the API if someone changes it and forgets to run `make front-api-types`. Nothing in CI checks it yet, since that needs a running API in the frontend job. A job that starts the stack, regenerates the file and fails on a difference is the follow-up.
- The tests use the fake API of `testing/fakeApi.ts` as they always did. MSW comes with E06-05 and will replace it for the screens.
- The access token never reaches TanStack Query or its devtools: queries receive the client, not the token.
