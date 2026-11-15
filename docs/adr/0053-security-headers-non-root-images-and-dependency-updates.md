# 0053. Security headers, images that do not run as root, and dependency updates

- Status: Accepted
- Date: 2026-11-14

## Context

The application checks who you are and what you may do (ADR 0008, ADR 0022), and keeps each tenant's data apart (ADR 0008). What it did not do yet is the part that sits around the code: what the browser is told about the answers it receives, what the containers are allowed to do, and how a flaw in a dependency reaches the project. E10-05 closes that, and writes down what is still open, against the OWASP Top 10:2025 ([the checklist](../security/owasp-checklist.md)).

Three questions come with it. Where the headers are added, since two servers stand in front of the browser: the web server of the frontend image and the API. How a container drops root without a privilege it would have to be given back. And which updates arrive by themselves.

## Decision

**The API adds its own security headers, in one listener** (`SecurityHeadersListener`, in `Shared/Infrastructure/Http`). Every answer carries `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`, `Permissions-Policy` (no camera, microphone, geolocation or payment) and `Cross-Origin-Resource-Policy: same-origin`. The answers of `/api` and `/health` also carry `Content-Security-Policy: default-src 'none'; frame-ancestors 'none'`, since they are data and nothing in them is meant to be loaded or framed. `/api/docs` is a page with its own scripts and styles, so it has every header but the policy. `Strict-Transport-Security` is sent only when the request came over HTTPS: a browser ignores it over HTTP, and a development server would otherwise tell it to insist on HTTPS for a year. A header an answer already has is left as it is.

It is a listener of the application and not a rule of the web server because the API is also reached directly (development, tests, a platform that puts only a proxy in front), and because a test can check it.

**The proxy that ends TLS is declared, not guessed.** `framework.trusted_proxies` reads `SYMFONY_TRUSTED_PROXIES`, empty by default. Without it the application sees plain HTTP behind a TLS proxy: the refresh cookie would not be marked `Secure` and the HSTS header would not be sent. With it empty, nobody is trusted, which is what a development machine wants.

**The web server of the frontend adds the headers of the files it serves.** The Caddyfile has a snippet, `security_headers`, imported by the two places that serve files (`/assets/*` and the rest). The paths it forwards to the API are left alone, because the API already adds its own, and the browser would otherwise see each one twice. The content policy lets the app load from its own address only: `default-src 'self'`, no inline scripts, `frame-ancestors 'none'`, `object-src 'none'`. Two things are wider than that. `style-src` keeps `'unsafe-inline'`, because Radix and Sonner put a `<style>` element in the page when they start, and moving them to a nonce would mean a server that writes `index.html` for each request. `Permissions-Policy` allows the camera for the origin (`camera=(self)`), which the scanner of the picking screen needs. `Strict-Transport-Security` is sent only when the proxy in front says `X-Forwarded-Proto: https`, since this server never sees TLS itself.

**Both production images run as a user without privileges** (ADR 0052 said nothing about it, and listed it as pending). The user is `app`, number 10001. For that:

- The containers listen on **8080**, not 80, so no capability has to be added to a binary to bind a low port. The `SERVER_NAME` of the backend image is `:8080` and the `PORT` of the frontend one defaults to 8080. This replaces the port 80 of ADR 0052. Development is not changed: Compose still maps 8080 on the host to 80 in the development container.
- The code in the backend image stays **owned by root**, and the process cannot change it. It owns `var/` only, which the build stage prepared. CI checks it can write in `var/log` and cannot write in `src/`.
- Caddy keeps its state in `/var/caddy`, which `app` owns. The directories the base images use (`/data`, `/config`) are declared volumes, and what a build changes in a volume is lost, so `XDG_DATA_HOME` and `XDG_CONFIG_HOME` point elsewhere.
- The entrypoint of the backend changes the ACLs of `var/` only when it runs as root, as in development.

**Dependabot** opens a pull request each Monday for Composer, npm, the Dockerfiles and the GitHub Actions, grouped to keep the number low, with titles that follow Conventional Commits (`build(deps): ...`, `ci(deps): ...`). `erp-legacy/` is left out on purpose: it is PHP 5.6 because the simulated ERP is meant to be old (ADR 0046). CI already audits the dependencies (`composer audit`, `npm audit` at high), and Dependabot is what brings the update.

**The checklist** of the OWASP Top 10:2025 is `docs/security/owasp-checklist.md`. Each line says what the project does, where to look, and when it does not do enough yet.

## Consequences

- CI checks that the headers are sent and that the processes are not root. It does not check that the app works under its content policy: that needs a browser, and a policy that blocks something shows as an error in the console. A change that adds a source of scripts, fonts or images has to widen the policy with it.
- An image that listens on 8080 changes the address of a deployment that followed ADR 0052. `API_UPSTREAM` of the frontend still says where the API is, and now it is `http://<host>:8080` for the backend image.
- `style-src 'unsafe-inline'` is a weakness that stays, and the checklist says so.
- **Rate limiting of the sign-in is not done.** It needs `symfony/rate-limiter`, which is not in the table of dependencies, and a new dependency is the author's decision. Until it is made, a password can be tried as often as the server allows. The checklist marks it as open.
- The SFTP client of the ERP import does not check the key of the server it connects to, and the simulated ERP has a password for development as a default in `services.yaml`. Both are in the checklist. A deployment against a real ERP must set the password and pin the key.
- Nothing scans the images for known flaws, and the GitHub Actions are referenced by tag and not by commit. Both are in the checklist as open.
