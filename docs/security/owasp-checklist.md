# Security checklist: OWASP Top 10:2025

What the project does about each of the ten risks of the [OWASP Top 10:2025](https://owasp.org/Top10/2025/0x00_2025-Introduction/), where to look to check it, and what is still open. It was written in E10-05 by reading the code, and the decisions behind it are in [ADR 0053](../adr/0053-security-headers-non-root-images-and-dependency-updates.md).

Status is **Done**, **Partial** (there is a control and it has a known gap) or **Open** (nothing yet). A line is not marked Done because a control exists but because something fails when it breaks: a test, a check in CI or a rule the build enforces.

## A01. Broken Access Control

| Control | Where | Status |
|---|---|---|
| Deny by default: a route of `/api` that no earlier rule names needs an operator to read it and a supervisor to change it | `config/packages/security.yaml` | Done |
| Roles `admin`, `supervisor` and `operator`, each including the ones below, decided per route and never inside a controller | `security.yaml`, ADR 0008 | Done |
| The role each operation says in the OpenAPI document is the one the firewall asks for | `OpenApiDocumentTest::testTheRoleOfEachOperationIsTheOneTheFirewallAsksFor` | Done |
| Tenant isolation by two walls: use cases check the tenant, and a Doctrine SQL filter adds it to every query on an entity with `tenantId`. A missing tenant is an error, never "all tenants" | `TenantFilter`, `TenantFilterTest`, `EnableTenantFilterTest` | Done |
| The tenant comes from the signed-in user, never from the request | `CurrentTenant` port, ADR 0008 | Done |
| The user is loaded from the database on every request, so a disabled user stops at once, whatever the token says | `DoctrineUserProvider`, `UserChecker` | Done |
| No endpoint takes a URL or a host from a request. The only outgoing connections go to addresses in the environment (the ERP, the Mercure hub), so there is nothing to make the server fetch (SSRF) | `Erp/Infrastructure`, `StreamHubTransport` | Done |
| The documentation of the API (`/api/docs`) is public, by design. It describes operations and holds no data | `security.yaml` | Partial |

## A02. Security Misconfiguration

| Control | Where | Status |
|---|---|---|
| Security headers on every answer of the API, and on the files of the frontend, with a content policy that lets the app load from its own address only | `SecurityHeadersListener`, `SecurityHeadersTest`, `frontend/Caddyfile`, the `Images` job | Done |
| Both production images run as a user without privileges, and the backend cannot change its own code | `backend/Dockerfile`, `frontend/Dockerfile`, the `Images` job | Done |
| `APP_DEBUG=0` in production, and an error that is not a rule of the domain answers a generic 500 problem, with the exception in the log and not in the answer | `ApiErrorListener` | Done |
| No secret in the repository: `.env` is not committed, the images hold none, and the configuration comes from the environment | `.gitignore`, `.dockerignore`, `Images` job (`test ! -s .env`, no JWT key) | Done |
| No CORS: the app and the API share one origin, so no other site is allowed in | ADR 0021, ADR 0052 | Done |
| The content policy allows inline styles, because Radix and Sonner write a `<style>` element | `frontend/Caddyfile` | Partial |
| `services.yaml` has a password for the simulated ERP as a default (`ERP_SFTP_PASSWORD`). A production environment has to set it | `config/services.yaml` | Partial |
| TLS is not done by the containers. The platform or a proxy ends it, and `SYMFONY_TRUSTED_PROXIES` has to name that proxy, or the refresh cookie is not marked `Secure` | ADR 0052, ADR 0053 | Partial |

## A03. Software Supply Chain Failures

| Control | Where | Status |
|---|---|---|
| The versions are fixed by `composer.lock` and `package-lock.json`, and installed with `npm ci` and `composer install` | `Dockerfile`s, CI | Done |
| `composer audit` and `npm audit --omit=dev --audit-level=high` fail the build when a dependency has a known flaw | `make validate`, `ci.yml` | Done |
| Dependabot opens a weekly pull request for Composer, npm, the Dockerfiles and the Actions | `.github/dependabot.yml` | Done |
| The production image has no Composer, Git or development packages | `frankenphp_prod`, `Images` job | Done |
| A new dependency needs the author's approval and a line in the table of `CLAUDE.md` | `CLAUDE.md` | Done |
| The GitHub Actions are referenced by tag (`@v4`), not by commit, so a moved tag would change what runs | `ci.yml` | Open |
| The images are not scanned for known flaws, signed, or given a list of their contents (SBOM) | | Open |

## A04. Cryptographic Failures

| Control | Where | Status |
|---|---|---|
| Passwords are hashed with the best algorithm the platform has (`auto`), and a password never leaves `PlainPassword`: it is not logged, printed or shown by a dump | `security.yaml`, `PlainPassword` | Done |
| Access tokens are RS256, signed with a private key that never leaves the server, and last one hour | `lexik_jwt_authentication.yaml`, ADR 0008 | Done |
| The secret of a refresh token is 32 random bytes, and only its hash is stored | `RandomRefreshSecretGenerator`, `RefreshToken::hash` | Done |
| The keys come from the environment in production, and the key pair is never committed | ADR 0008, `.gitignore` | Done |
| Encryption in transit is the job of whatever stands in front of the containers | ADR 0052 | Partial |
| Nothing is encrypted at rest by the application. The disk and the database are the platform's | | Open |

## A05. Injection

| Control | Where | Status |
|---|---|---|
| Doctrine ORM and DBAL with bound parameters. The text that is joined into a query is the name of a column written in the code, and the numbers of a page, which are typed | `Dbal*` read models, `DbalErpImportLog`, `DbalShipmentOutbox` | Done |
| The body of every operation is a typed DTO and is validated before it reaches a command | API Platform resources, ADR 0006 | Done |
| The frontend writes no HTML by hand (`dangerouslySetInnerHTML` is not used), and React escapes what it shows | `frontend/src` | Done |
| The content policy of the frontend refuses inline scripts, which limits what an injected page could do | `frontend/Caddyfile` | Done |
| The files of orders from the ERP are read as data and translated before they enter the domain | `ErpOrderTranslator`, its test | Done |

## A06. Insecure Design

| Control | Where | Status |
|---|---|---|
| The business rules come first and have an ID each (`STK-01`, `OUT-10`), with a test that proves it | `docs/domain/aggregates.md` | Done |
| Stock is a ledger of movements that are never changed, and it can never be negative | `docs/domain/aggregates.md`, the `STK` rules | Done |
| Tenants are separated by design and not by convention | ADR 0008, A01 | Done |
| Every architecture decision has an ADR, and the rules of the layers are enforced by PHPat | `docs/adr/`, `ArchitectureTest` | Done |
| There is no written threat model | | Open |
| The sign-in is limited: five tries for each email from each address and twenty-five from an address, in fifteen minutes | `login_throttling` in `security.yaml`, `AuthApiTest`, ADR 0054 | Done |
| The rest of the API is not limited per client | | Open |

## A07. Authentication Failures

| Control | Where | Status |
|---|---|---|
| A wrong password, an unknown email and a disabled account get the same answer, so it does not tell whether an account exists | `AuthenticationFailureListener` | Done |
| A password has at least 12 characters and at most a fixed number of bytes | `PlainPassword` (IDN-03) | Done |
| The refresh token rotates at each use, and a token just spent still counts for a few seconds as a second request of the same client | ADR 0022 | Done |
| The refresh token is a cookie `HttpOnly`, `SameSite=Strict`, sent only to the sign-in routes. The access token lives in memory and never in `localStorage` | `RefreshCookie`, `AuthClient` | Done |
| The cookie is `Secure` when the request is HTTPS, which behind a TLS proxy needs `SYMFONY_TRUSTED_PROXIES` | `RefreshCookie`, `framework.yaml` | Partial |
| A password cannot be tried as often as the server allows: after five wrong tries the sign-in answers 429, whatever the password, and an email that does not exist counts like one that does | `login_throttling`, `AuthenticationFailureListener`, `AuthApiTest` | Done |
| The limit does not slow an attack from many addresses, and the count of an email takes spaces around it as another email | ADR 0054 | Partial |
| No second factor | | Open |

## A08. Software or Data Integrity Failures

| Control | Where | Status |
|---|---|---|
| Migrations are never edited once committed, a mistake is fixed with a new one | `CLAUDE.md` | Done |
| Changes reach `main` by a pull request with its checks, and the title follows Conventional Commits | CI, `pr-title` job | Done |
| Events are published after the command commits, and the policies that react to them are idempotent | ADR 0012 | Done |
| Confirmations to the ERP go through an outbox with retries, so one is not lost nor sent twice by a failure half way | ADR 0049 | Done |
| The audit trail writes down every domain event in the transaction of the change | ADR 0018 | Done |
| The SFTP client of the ERP import does not check the key of the server. A person in the middle of that connection could read the password and change the files | `SftpErpOrderInbox` | Open |
| The images are not signed | | Open |

## A09. Security Logging and Alerting Failures

| Control | Where | Status |
|---|---|---|
| Every domain event is recorded with who caused it, and a field that is personal data is marked `#[Sensitive]` | ADR 0018 | Done |
| The audit trail can be read by supervisors and admins | `GET /api/audit-log` | Done |
| An unexpected error is logged with its exception | `ApiErrorListener` | Done |
| Failed sign-ins and refused requests are not written down as security events | | Open |
| Logs are not structured and nothing raises an alert | E10-04 | Open |

## A10. Mishandling of Exceptional Conditions

| Control | Where | Status |
|---|---|---|
| A rule of the domain that is broken is an exception with a stable code, answered as a translated `application/problem+json` with the right status | `DomainRuleViolation`, ADR 0007 | Done |
| A command runs in a transaction, and a failure rolls it back | ADR 0009 | Done |
| An unexpected error answers a generic 500 without detail | `ApiErrorListener` | Done |
| A 401 and a 403 are never made by hand: they go through `ProblemFactory`, so a failure of security answers closed and with the same shape | `CLAUDE.md`, `AccessDeniedHandler` | Done |
| A dependency that is down is reported, not hidden: `/health` answers 503 with the checks that failed, and CI starts the image with nothing behind it to prove it | `HealthController`, `Images` job | Done |
| A failure of the ERP stops the import for that run and the next one starts again from the same place. A message that fails every retry goes to a table where it can be listed and handled again | ADR 0012, ADR 0048 | Done |

## What is open, in order

1. **Check the SFTP server key** of the ERP import (A08), and replace the default ERP password in any real environment (A02).
2. **Pin the Actions to a commit**, scan the images, and sign them (A03, A08).
3. **Record failed sign-ins and refusals**, and structure the logs (A09, with E10-04).
4. Write a short threat model, and decide whether the rest of the API needs a limit per client (A06).
