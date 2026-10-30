# 0039. Playwright end-to-end tests with a simulated API

- Status: Accepted
- Date: 2026-10-30

## Context

The unit and screen tests (Vitest, Testing Library, MSW; ADR 0037) run in jsdom. They cannot tell whether the production build loads, whether the router and the session survive a real navigation, or whether a screen works in a real engine. E06-06 asks for a test of the main flow in CI.

## Decisions

- **Playwright, in Chromium and Firefox.** Both engines run every spec in CI. WebKit is left out until the picking PWA (E07) needs it for iOS.
- **The production build.** `npm run test:e2e` builds the app and serves it with `vite preview` on port 4173, so the tests exercise the files that are deployed, not the dev server.
- **A simulated API inside the test.** `e2e/support/backend.ts` is an in-memory fake with state, installed with `page.route('**/api/**')`. It answers the same paths and shapes as the real API (products, deliveries, receipts, orders, waves, pick lists, `X-Total-Count`), and knows only what the flow needs. The domain rules are tested in the backend; here we test that the screens, the router and the session carry a person through the flow.
- **One main flow and a few access checks.** The flow is delivery, receipt, order, wave: the supervisor creates a product with its barcode, announces a delivery, counts it at the dock, closes the receipt, sees the stock, registers an order, reserves it and releases a wave. The access specs cover the redirect to sign-in, a wrong password and the operator, who sees no create buttons.
- **Locators by role and name**, the same ones a screen reader uses, so the specs double as a check of the accessible names.
- **A separate CI job** (`Frontend E2E`) so a browser failure does not hide the result of lint and unit tests. The HTML report is kept as an artifact when it fails.
- **`E2E_CHROMIUM_PATH`** lets a machine that cannot download the browsers use one that is installed.

## Consequences

- The fake and the real API can drift apart. The OpenAPI schema types the unit tests but not this fake; a job against the real stack is planned for E10 and would replace it for the flow.
- The job downloads two browsers (cached by Playwright per run); it takes a few minutes.
- `e2e/` and `playwright.config.ts` are type-checked (`tsconfig.e2e.json`) and linted like the rest.
