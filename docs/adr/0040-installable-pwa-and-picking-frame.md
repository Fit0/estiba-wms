# 0040. Installable PWA and a frame for picking

- Status: Accepted
- Date: 2026-10-30

## Context

Operators pick with a phone. E07 turns the frontend into a PWA: installable, usable with a finger and, later, with a scanner and without a connection. This first step makes the app installable and gives the operator a screen made for a phone. The scanner (E07-02) and the offline queue (E07-03) build on it.

## Decisions

- **`vite-plugin-pwa` with `generateSW`.** The manifest lives in `vite.config.ts` and the service worker is generated from it. There is no custom worker code to maintain yet; if E07-03 needs one (background sync) the plugin switches to `injectManifest` without touching the rest.
- **The service worker caches the app shell and nothing else.** Scripts, styles, the HTML, the icons and the Latin subsets of the two fonts are precached. `/api` and `/health` are excluded from the navigation fallback and never cached: a stale answer from a cache is worse than none for stock and picking data. What an operator does without a connection is the queue of E07-03.
- **The new version waits for the person.** `registerType: 'prompt'` and `UpdatePrompt` show a notice with an "Update" button. An automatic reload could throw away a half-finished pick. The same component announces that the app can be opened without a connection.
- **`start_url` is `/picking`.** The installed app opens where the operator works. The manifest declares `display: standalone`, dark colors that match the canvas token, and 192 and 512 px icons plus a maskable one (the mark is inside the safe area).
- **A frame of its own for picking.** `/picking` sits under `RequireAuth` but outside `AppLayout`: a bar with the connection and the theme, the page, and a way back to the dashboard. No side navigation. While it is on screen the density is `touch` (48 px controls, ADR 0030); the density the person chose for the back office is restored when they leave, not overwritten.
- **The first picking screen only reads.** It lists the pick lists that are still to do, split into the operator's own and those nobody has taken, and refreshes every 15 s like the waves. Taking a list and picking line by line arrive with the scanner.

## Consequences

- The first visit has no worker, so "works offline" is true from the second load on. The E2E tests check the manifest, the active worker, the reload without a connection (Chromium) and the picking screen on a phone viewport.
- A service worker only runs on HTTPS or `localhost`; the production deployment (E10) is HTTPS anyway.
- `GET /api/pick-lists` returns the lists of the whole tenant and the screen filters them. A filter by operator in the API would be better once there are many lists; it is a backend task to add when it matters.
- The icons are PNGs generated from an SVG mark once and committed under `public/`.
- When the production image arrives (E10-01), the server must send `sw.js`, `manifest.webmanifest` and `index.html` with `Cache-Control: no-cache`, and the hashed files under `assets/` as immutable. A worker cached for a year never updates.
