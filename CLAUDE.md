# CLAUDE.md

Guidance for Claude Code when working in this repository. Read it before changing anything.

Estiba WMS is an open-source Warehouse Management System and the flagship of the author's professional portfolio. Code quality, clear history and documentation matter as much as features: reviewers will read this code.

## Tech stack

| Layer | Technology | Status |
|---|---|---|
| Language | PHP 8.4 (`composer.json` requires `>=8.4`) | In use |
| Framework | Symfony 7.4 LTS | In use |
| Web server | FrankenPHP 1.x (Caddy), classic mode | In use |
| Persistence | Doctrine ORM 3, Doctrine Migrations, PostgreSQL 17 | In use |
| Cache and queues | Redis 7.4 (phpredis extension) | In use |
| Testing | PHPUnit 13 | In use |
| Static analysis and style | PHPStan 2 (max level), PHP-CS-Fixer, Rector 2 | In use |
| Architecture tests | PHPat 0.12 (runs inside PHPStan, rules in `backend/tests/Architecture/`) | In use |
| API | API Platform 5, plain JSON, DTO resources and our own state providers and processors (ADR 0006) | In use |
| Messaging | Symfony Messenger: command and query buses ([ADR 0009](docs/adr/0009-command-and-query-buses-with-symfony-messenger.md)) and an event bus for policies. Events wait for the command to commit; the routed ones go to a Redis stream (`symfony/redis-messenger`) handled by the `worker` service with retries, and the ones that keep failing to a PostgreSQL table (`symfony/doctrine-messenger`) ([ADR 0012](docs/adr/0012-asynchronous-policies-with-a-redis-transport.md)) | In use |
| Auth | Symfony Security and LexikJWTAuthenticationBundle (RS256 access tokens, one hour), renewed with a rotating refresh token in an `HttpOnly` cookie ([ADR 0022](docs/adr/0022-sessions-with-a-rotating-refresh-token-in-a-cookie.md)) | In use |
| Translations | Symfony Translation (ICU format) on the backend, `react-i18next` on the frontend. Spanish (default) and English. | Backend in use. Frontend: a small catalog with the shape of `t` until `react-i18next` replaces it (E05-06) |
| Frontend | React 19, TypeScript (strict), Vite, in Docker behind a proxy to the API ([ADR 0021](docs/adr/0021-the-frontend-project-in-docker-behind-a-proxy.md)) | In use (E05-02) |
| Frontend libraries | React Router | In use (E05-03) |
| | TanStack Query, React Hook Form + Zod, shadcn/ui + Tailwind | Planned (E05-04 to E05-07) |
| Frontend quality | ESLint 9 (`typescript-eslint` strict, `react-hooks`, `jsx-a11y`), Prettier, Testing Library | In use (E05-02) |
| Frontend testing | Vitest (in use since E05-02), MSW, Playwright | MSW and Playwright planned (E06) |
| Real time | Mercure | Planned (E07) |
| CI | GitHub Actions | In use |

Do not add a dependency that is not in this table without asking first. When one is approved, add it here.

## Architecture

Hexagonal architecture with DDD, organized by bounded context under `backend/src/`:

```
src/
├── Shared/                  Cross-cutting code (health check, base infrastructure)
└── <Context>/               Identity, Catalog, Warehouse, Inventory, Inbound, Outbound, Audit
    ├── Domain/              Entities, value objects, domain events, repository interfaces (ports)
    ├── Application/         Use cases: commands, queries and their handlers
    └── Infrastructure/      Adapters: Doctrine repositories, XML mappings, HTTP controllers, API resources
```

Dependency rule: `Infrastructure → Application → Domain`. Never the other way round.

Both this rule and the context boundaries below are enforced by `backend/tests/Architecture/ArchitectureTest.php` through PHPat, so they run in `make stan` and in CI. A new bounded context must be registered in its `DEPENDS_ON` map, which also says which contexts it may depend on. Never weaken a rule to make a change pass: move the code instead.

- `Domain` has **no** framework or library imports: no Symfony, no Doctrine, no attributes from either. Plain PHP only.
- Doctrine mapping lives in XML under each context's `Infrastructure/Persistence/Doctrine/` and is registered in `config/packages/doctrine.yaml`. There is no `src/Entity`.
- Controllers are invokable, `final readonly`, one action each, and only translate HTTP to a command or query.
- Every use case is a `Command` or a `Query` class plus a `...Handler` that implements `CommandHandler` or `QueryHandler`, in `Application/<UseCase>/`. Entry points (API Platform processors and providers, console commands) send them through `CommandBus::dispatch()` and `QueryBus::ask()` and never call a handler. The command bus runs each command in a transaction and flushes, so **handlers and repositories never flush**. A command returns what it created or changed, and a query never changes state. The architecture test enforces this.
- A policy ("whenever X happens, do Y") is an `EventHandler` in `Application/`, invoked with one domain event. A command handler or a policy **must publish** the events of every aggregate it changes, with `EventBus::publish(...$aggregate->pullEvents())`: the policies and the audit trail only know what is published, and in development and in the tests a guard fails any command or policy that leaves events behind (ADR 0018). The events leave after the command commits, and each policy runs in its own transaction (ADR 0012). A new event carries its `tenantId`, since a policy that runs in a worker has no signed-in user to take it from.
- A query that shows data of several contexts reads a projection, never a join across their tables: a table owned by the context that answers, filled by a policy from the other contexts' contracts, written and read with plain SQL in `Infrastructure/Persistence/ReadModel`, and rebuildable from the source (see the stock read model, ADR 0013).
- An event routed to `async` in `config/packages/messenger.yaml` is handled by the worker, maybe more than once: its policy must be idempotent (record in its own transaction that it ran, and do nothing the second time), have one handler per event, and take everything it needs from the event, since there is no signed-in user and no tenant filter. After changing a policy, `make worker-restart`.
- The audit trail writes down every domain event that is published, through a decorator of the event bus, in the transaction of the change ([ADR 0018](docs/adr/0018-audit-trail-of-every-domain-event.md)). A field of an event that is personal data carries `#[Sensitive]`. A new event needs nothing else.
- API resources are DTOs in `Infrastructure/Http/Resource`, never domain entities. Each operation is a state provider (query bus) or processor (command bus) in `Http/State` (ADR 0006, ADR 0009). The tenant comes from `CurrentTenant`, never from the request body.
- Every API operation has an entry in `backend/config/api/operations.yaml`: a summary, the rules, the role, an example of the body and the filters of a list. The OpenAPI document is completed from that table, and a test fails when an operation has no entry, an example does not fit, or the role it says is not the one the firewall asks for ([ADR 0019](docs/adr/0019-openapi-document-written-by-hand-and-checked.md)).
- A context may depend on another one only through that context's `Application/Contract` namespace (interfaces, DTOs and integration events). Never on its `Domain`, `Infrastructure` or the rest of its `Application`. Reference other contexts' data by id value, with no foreign keys or joins across contexts (ADR 0003).
- `Shared` may be used by every context and must not depend on any of them.
- Every architecture decision gets an ADR in `docs/adr/` (start from `docs/adr/template.md`).

## Domain model

The business model lives in `docs/domain/` and comes before the code: [ubiquitous language](docs/domain/ubiquitous-language.md), [event storming](docs/domain/event-storming.md), [aggregates and invariants](docs/domain/aggregates.md) and the [context map](docs/domain/context-map.md). Read the relevant part before implementing a context.

- Use the terms of the ubiquitous language in class, method and test names. Do not invent synonyms.
- Each invariant has an ID (`STK-01`, `OUT-10`…). Reference it in the test that proves it and in a short comment where the code enforces it.
- If the code needs a rule the documents do not have, or contradicts one, update the document in the same pull request.

## Code conventions

### PHP

- `declare(strict_types=1);` in every file.
- Classes are `final` by default; services and value objects are `readonly`.
- Constructor property promotion and dependency injection through the constructor. No service locator, no static state.
- Value objects for domain concepts (`LocationCode`, `Gtin`, `Sku`, `Quantity`). No primitive obsession in the domain.
- Methods do one thing and stay short: aim for under 20 lines, refactor beyond 30.
- Strict comparisons only (`===`). No short ternary (`?:`). Only booleans in conditions.
- Native types everywhere; PHPDoc only for what native types cannot express (generics, array shapes).
- Imports: one `use` per class, ordered class, function, const. No unused imports. PHP-CS-Fixer enforces this.
- Domain errors are specific exceptions named after the rule they protect (`NegativeStockNotAllowed`), not generic `\Exception`.
- Code, comments, identifiers, commit messages and documentation are in **English**. Text the user sees is not: see "Languages and translations".

### PHPStan

- Level `max` with strict rules. **Do not** add `@phpstan-ignore`, baselines or `ignoreErrors` to make a check pass. Fix the code. If something truly cannot be fixed, stop and explain why.

### TypeScript (from E05)

The frontend lives in `frontend/` and runs in Docker like everything else: never run `npm` on the host. `make front-qa` runs what the `Frontend` job of CI runs, and it must pass before proposing a commit.

- `strict: true`. **No `any`**: use `unknown` and narrow it. No non-null assertions (`!`) to silence the compiler. ESLint fails both. Do not add `eslint-disable` or `@ts-ignore` to make a check pass: fix the code, as with PHPStan.
- Components call the API through relative paths (`/api/...`): the dev server forwards them to the backend, so there is no CORS and no address in the code.
- API types are generated from the OpenAPI schema, never written by hand.
- Server state goes through TanStack Query, never copied into local state. (The session of E05-03 is the exception until E05-04 brings TanStack Query.)
- The access token lives only in memory (`AuthClient`), never in `localStorage`, `sessionStorage` or a cookie of the app. The refresh token is a cookie the server sets and JavaScript cannot read (ADR 0022).
- Function components and hooks only.

### Tests

- Every domain rule has a unit test. Every endpoint has a functional test.
- Test names describe behavior: `testRejectsMovementThatMakesStockNegative`.
- Unit tests in `tests/Unit/<Context>/`, functional tests in `tests/Functional/<Context>/`.
- The domain never generates identifiers or reads the clock. Identifiers (UUID v7) and `\DateTimeImmutable` values are passed in, and unit tests use `Ids::next()` and fixed dates.
- Rules that need to look at other aggregates (such as a unique code) go through a small registry interface that the factory receives, so a unit test can fake it. A unique database constraint backs it against races.
- Build test objects with a "mother" class (for example `WarehouseMother`) so each test spells out only what it is about.
- Functional tests get an empty database from `TestDatabase`: the schema is built once per run and every test only truncates the tables. `ApiTestCase` also disables kernel reboot between requests, so anything that keeps state across requests must be reset at the start of each one (see `ResetTenantFilter`). A new table needs nothing: `TestDatabase` reads the tables from the mapping.
- Demo data (`make demo`, `src/Demo/`) is a fashion company, Marea Moda, built through the domain and available only in `dev` and `test`. No context may depend on `App\Demo`; the architecture test enforces it. Its tests double as a check that the domain accepts a realistic catalog and warehouse.

### Languages and translations

The interface is bilingual: Spanish (`es`, the default) and English (`en`). The reasoning is in [ADR 0004](docs/adr/0004-bilingual-interface-spanish-by-default.md).

- Never write user-visible text in code. In the frontend use a translation key. In the backend use the Translation component. Do not build a sentence by gluing fragments, and use ICU for plurals.
- Domain exceptions carry a **stable error code** and parameters, never a sentence. They extend `DomainRuleViolation` (in `Shared\Domain`), which implements `DomainError`, and are built with a named constructor that holds the code, its parameters and an English message for logs (see `Warehouse/Domain/Exception/`). Codes are lowercase and dotted: `inventory.stock_negative`, `warehouse.location_code_invalid`. The architecture test fails any domain exception that does not implement `DomainError`.
- Domain tests assert the error code and its parameters, not the message (`AssertsDomainErrors` in `tests/Unit/Support/`).
- Every new code or key needs an entry in both `es` and `en`. A parity test fails the build when one is missing.
- Backend error texts live in `backend/translations/errors+intl-icu.es.yaml` and `.en.yaml`, keyed by error code, in ICU format. A parameter that picks the wording (a field name, a status) is an ICU `select`. **Avoid apostrophes**: ICU treats them as special. `ErrorCatalogTest` reads every code from the source and fails when a text is missing, left over, invalid ICU, or uses a parameter the error does not carry. How to add a code: [docs/api/errors.md](docs/api/errors.md).
- Every API error is `application/problem+json`, built only by `ProblemFactory` through `ApiErrorListener` ([ADR 0007](docs/adr/0007-one-translated-problem-shape-for-every-api-error.md)). Errors that are not domain rules use the generic `shared.*` codes of the `HttpProblem` enum. Never return an error response by hand.
- The language comes from `Accept-Language`: Spanish by default, English on request. In functional tests set `$this->acceptLanguage`, which defaults to an empty header, because the test client otherwise adds `en-us` on its own.
- Spanish and English UI terms follow the [ubiquitous language](docs/domain/ubiquitous-language.md). Do not invent translations for terms that are in it.
- Data users type (product descriptions, supplier names) is stored as typed and not translated.

## Commands and workflows

Everything runs inside Docker. Use `make` from the repository root (`make` alone lists all targets).

| Command | Purpose |
|---|---|
| `make up` / `make down` | Start (and wait until healthy) / stop the stack |
| `make sh` | Shell in the PHP container |
| `make console c="..."` | Symfony console, e.g. `make console c="debug:router"` |
| `make composer c="..."` | Composer, e.g. `make composer c="require vendor/package"` |
| `make test` | PHPUnit (creates the `estiba_test` database if missing) |
| `make stan` | PHPStan |
| `make cs` / `make rector` | Style and refactoring checks (dry run) |
| `make fix` | Apply Rector and PHP-CS-Fixer |
| `make qa` | All checks, backend and frontend. **Must pass before proposing a commit.** |
| `make front-qa` | The frontend checks only: lint, format, types, tests and build (the `Frontend` job of CI) |
| `make front-npm c="..."` | npm in the frontend container, e.g. `make front-npm c="install foo"`; commit `package-lock.json` after |
| `make front-sh` / `make front-format` | Shell in the frontend container / apply Prettier |
| `make worker-logs` / `make worker-restart` | Follow / restart the worker that handles the asynchronous events |
| `make failed` / `make retry-failed` | List / handle again the events that failed every retry |
| `make stock-rebuild` | Build the stock read model again from the ledger (after a migration that creates it, or if it drifts) |

If `make` is not available in the shell, run the equivalent `docker compose exec -T php ...` command from the `Makefile`.

### Database migrations

```bash
make console c="doctrine:migrations:diff"      # generate from mapping changes
make console c="doctrine:migrations:migrate -n"
```

Review every generated migration before keeping it. Committed migrations are never edited; fix mistakes with a new migration.

### Git workflow

- Commits follow Conventional Commits, enforced by `.githooks/commit-msg` (`git config core.hooksPath .githooks`). Types and scopes are listed in `CONTRIBUTING.md`.
- Commit messages end with a `Co-Authored-By` line when Claude contributed.
- **The author commits and pushes.** When a task is done, run `make qa`, then propose the commit message and stop. Do not commit, push, create branches on the remote or open PRs unless explicitly asked.

### Environment notes (Windows host)

- `vendor/` and `var/` live in Docker volumes for speed. Never run Composer on the host. To refresh the IDE copy: `docker compose cp php:/app/vendor backend/`.
- PostgreSQL is exposed on host port **5433**; the API on **8080**.
- Git for Windows runs with `core.fileMode=false`, so the executable bit of scripts is not reliable in commits. CI and any other Linux consumer must call scripts through their interpreter (`php bin/console`, `sh script.sh`) and never rely on `bin/console` being executable.
- In Git Bash, prefix `docker` commands that take absolute container paths with `MSYS_NO_PATHCONV=1`.

## Business rules and constraints

### Domain invariants

- **Inventory is a ledger.** Stock changes only through immutable movements. Never update or delete a movement; correct it with a compensating movement.
- **Stock can never be negative** in any location.
- GTIN and SSCC values are validated with the GS1 check digit before they enter the domain.
- Location codes follow `aisle-module-level` (e.g. `A-03-02`).
- Allocation uses FEFO for products with expiry dates and FIFO otherwise.
- Every tenant's data is isolated, by two walls. Use cases check the tenant, and a Doctrine SQL filter (`TenantFilter`) adds it to every query on an entity with a `tenantId` field. A missing tenant is an error, never "all tenants".
- **The tenant always comes from the signed-in user**, through the `CurrentTenant` port, never from the request: no header, parameter or body field may name a tenant. A new entity with a tenant must call its field `tenantId`, or the filter skips it, and `TenantFilterTest` fails.

### Files never to modify by hand

| File | Why |
|---|---|
| `backend/config/bundles.php`, `backend/config/preload.php`, `backend/config/reference.php`, `backend/public/index.php` | Managed or generated by Symfony Flex. Running the tests regenerates `reference.php` without its `when@dev` entries: that diff is noise, discard it with `git checkout -- backend/config/reference.php`. A diff that adds a new bundle's configuration types is real and must be committed. |
| `backend/symfony.lock`, `backend/composer.lock` | Changed only through Composer |
| Committed files in `backend/migrations/` | History of the schema; add a new migration instead |
| `backend/.env`, `backend/.env.dev` | **Never committed**: they hold `APP_SECRET` and `JWT_PASSPHRASE` and are git-ignored. `backend/.env.example` is the committed template, with no secrets. `make env` (run by `make up`) creates `.env` from it with new random secrets, and CI does the same through `backend/scripts/create-env.sh`. When a Composer recipe adds variables to `.env`, copy them to `.env.example`. Real secrets in production come from the deployment platform. |
| `backend/config/jwt/*.pem` | The JWT key pair. Git ignores it, never commit or print it. Generate it with `make jwt-keys`. |
| `LICENSE` | Legal text |

### Security

- No secrets, tokens or real customer data in the repository, fixtures or tests.
- AI features (E09) only call read-only queries and never send tenant data the user did not request.
- Authentication and roles are decided in [ADR 0008](docs/adr/0008-jwt-authentication-and-tenancy-from-the-signed-in-user.md). Roles are `admin`, `supervisor` and `operator`, each including the ones below. Reading needs any signed-in user and changing needs a supervisor, except the dock work that operators do (opening, counting and closing a receipt, confirming and splitting putaway tasks), and those rules live in `access_control` in `config/packages/security.yaml`. Refine it there, per route, never inside a controller.
- A password never leaves `PlainPassword`: do not log it, print it, put it in an error, or take it as a console argument. Never trust the roles or the tenant in a token: the user is loaded from the database on every request.
- Never return a 401 or a 403 by hand. They go through `ProblemFactory`, so they are translated and keep the common shape.
- The API tests send real tokens: `ApiTestCase::request()` acts as an admin of `$this->tenant`, `$this->role` changes the role, and an empty tenant sends no token.

### Accessibility (frontend, from E05)

- Target WCAG 2.2 AA: semantic HTML, labels on every input, visible focus, contrast of at least 4.5:1.
- Picking PWA is used with gloves on handheld scanners: touch targets of at least 48×48 px, every action reachable with a hardware scanner or keyboard, feedback by sound and vibration as well as color.
