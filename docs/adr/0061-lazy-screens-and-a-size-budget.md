# 0061. Lazy screens and a size budget

- Status: Accepted
- Date: 2026-11-17

## Context

The build put the whole app in one JavaScript file of 984 kB (279 kB gzip) and warned about it. The sign-in page and the first screen downloaded every screen of the app, the ones for admins and for the picking PWA included. The only other file, ZXing (454 kB), was already split off: it is imported when the camera needs it.

There is no charting library in the app (the dashboard draws its own bars), so the weight is the screens plus the libraries they share: React, the router, TanStack Query, Radix, the forms and the catalogs.

## Options considered

1. **Leave it.** A back-office app on a good connection hardly notices 280 kB, but the picking PWA runs on a phone, and the build keeps warning.
2. **Route-level lazy loading with the router (`lazy` on the route).** The router waits for the file before it renders the first match, so it needs a fallback for the very first render, and the "recovering the session" status of `RequireAuth` would come second.
3. **`React.lazy` for every screen, with a `Suspense` boundary in each layout.** The frame (menu, top bar, session status) draws at once and only the page waits.
4. **Split the libraries by hand (`manualChunks`).** It helps the cache, not the first load, and it ties the build to a list of package names. Left for later, if the numbers ask for it.

## Decision

Option 3. In `routes.tsx` every screen except signing in and "not found" is written with `page(() => import(...), 'Name')`, and `AppLayout` and `PickingLayout` wrap the `Outlet` in `Suspense` with `RouteLoading`, a `role="status"` line. A test reads `routes.tsx` and fails when another screen is imported statically.

The size is checked by `npm run size` after the build, in `make front-qa` and in CI. It adds up the gzip size of what index.html loads at once (the initial JavaScript) and compares it with `frontend/bundle-budget.json`, and it limits each lazy file. A change that makes the first load heavier fails the build instead of waiting for someone to read the Vite warning.

The service worker keeps precaching every file (`globPatterns`): the operator must reach any picking screen without a connection (ADR 0040), and precaching happens in the background, after the first screen has loaded. Lazy loading changes what the first visit downloads, not what is available offline.

## Result

The first load went from 279 kB to 220 kB of gzip JavaScript, and the main file from 984 kB to 418 kB (130 kB gzip). The build no longer warns. The budget is 240 kB for the initial JavaScript, about 10 % over what it weighs, and 130 kB for any lazy file (ZXing, the heaviest, is 118 kB). The numbers are in [docs/performance](../performance/results.md).

## Consequences

- Signing in and the first screen download the frame and the shared libraries, not the 19 screens.
- A screen takes one more request the first time it is opened. The status is announced, and the file is cached by the service worker.
- A new screen follows the same line in `routes.tsx`; the test says so if it does not.
- The budget has to be moved on purpose, with the reason in the pull request.
