# Frontend

React 19 + TypeScript single-page app for back-office operations, plus the picking PWA for warehouse operators. The decisions are in [ADR 0021](../docs/adr/0021-the-frontend-project-in-docker-behind-a-proxy.md).

It runs in Docker with the rest of the stack, so there is nothing to install:

```bash
make up                  # starts everything, the frontend included
```

Then open **http://localhost:5173**. The first start installs the dependencies and takes a few minutes. Changes to the files are picked up by the browser at once.

The app talks to the API through the same address: the development server forwards `/api` and `/health` to the backend, so there is no CORS to set up. Try it: `http://localhost:5173/health`.

## Commands

| Command                                 | Purpose                                                     |
| --------------------------------------- | ----------------------------------------------------------- |
| `make front-qa`                         | Everything CI checks: lint, format, types, tests and build  |
| `make front-lint` / `make front-format` | ESLint / apply Prettier                                     |
| `make front-typecheck`                  | TypeScript                                                  |
| `make front-test`                       | Vitest                                                      |
| `make front-api-types`                  | Regenerate the API types from the running API (ADR 0025)    |
| `make front-npm c="install <package>"`  | npm in the container. Commit `package-lock.json` afterwards |
| `make front-sh`                         | A shell in the container                                    |

## Layout

```
frontend/
├── src/                     The app: main.tsx, routes.tsx, auth/ (session, guards), api/ (typed client, cache, hooks), pages/, layouts/ (sidebar and top bar), components/ (button, field, page title, data table, status badge, KPI card), i18n/, theme/
│   ├── index.css            Tailwind and the theme of the design
│   └── styles/tokens.css    The design tokens, in the dark and the light theme
├── vite.config.ts           Vite, the proxy to the API and Vitest
├── eslint.config.js         ESLint: strict TypeScript, React hooks and accessibility
├── tsconfig.*.json          TypeScript: strict, one config for the app and one for the tools
├── Dockerfile               The development image (Node 24)
└── docker-entrypoint.sh     Installs the dependencies when package-lock.json changes
```

## Rules

- No `any` and no `!`: ESLint fails them. Use `unknown` and narrow it.
- The look is the design system of [ADR 0023](../docs/adr/0023-design-tokens-and-two-themes.md): colors, sizes and radii come from the tokens, never from a value of their own, and both themes must work.
- The session (`src/auth/`) keeps the access token in memory and renews it with the cookie of the refresh token (ADR 0022). Routes are in `src/routes.tsx`: sign-in is the only public one, and `RequireRole` guards by role (what to show; the API is what refuses).
- The data components (`DataTable`, `Pagination`, `StatusBadge`, `KpiCard`) are controlled and keep no state: the sort, the selection and the page are the caller's ([ADR 0024](../docs/adr/0024-controlled-data-components-and-a-dev-gallery.md)). To see them with sample data, run the app and open `/design` (development only).
- Server data goes through TanStack Query, and the types of the API are generated from its OpenAPI document ([ADR 0025](../docs/adr/0025-a-typed-api-client-and-a-cache-of-server-data.md)). With the stack up, `make front-api-types` regenerates `src/api/schema.d.ts`; run it after any change to the API and commit the result. A new resource gets a file in `src/api/` with its keys and its hooks, like `stock.ts`.
- Target WCAG 2.2 AA: semantic HTML, a label on every input, visible focus, contrast of at least 4.5:1.
