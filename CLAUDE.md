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
| API | API Platform | Planned (E02) |
| Messaging | Symfony Messenger (command and query buses), Redis transport | Planned (E03) |
| Auth | LexikJWTAuthenticationBundle | Planned (E02) |
| Frontend | React 19, TypeScript (strict), Vite, TanStack Query, React Router, React Hook Form + Zod, shadcn/ui + Tailwind | Planned (E05) |
| Frontend testing | Vitest, Testing Library, MSW, Playwright | Planned (E06) |
| Real time | Mercure | Planned (E07) |
| CI | GitHub Actions | Planned (E01-04) |

Do not add a dependency that is not in this table without asking first. When one is approved, add it here.

## Architecture

Hexagonal architecture with DDD, organized by bounded context under `backend/src/`:

```
src/
├── Shared/                  Cross-cutting code (health check, base infrastructure)
└── <Context>/               Catalog, Warehouse, Inventory, Inbound, Outbound
    ├── Domain/              Entities, value objects, domain events, repository interfaces (ports)
    ├── Application/         Use cases: commands, queries and their handlers
    └── Infrastructure/      Adapters: Doctrine repositories, XML mappings, HTTP controllers, API resources
```

Dependency rule: `Infrastructure → Application → Domain`. Never the other way round.

Both this rule and the context boundaries below are enforced by `backend/tests/Architecture/ArchitectureTest.php` through PHPat, so they run in `make stan` and in CI. A new bounded context must be registered in its `DEPENDS_ON` map, which also says which contexts it may depend on. Never weaken a rule to make a change pass: move the code instead.

- `Domain` has **no** framework or library imports: no Symfony, no Doctrine, no attributes from either. Plain PHP only.
- Doctrine mapping lives in XML under each context's `Infrastructure/Persistence/Doctrine/` and is registered in `config/packages/doctrine.yaml`. There is no `src/Entity`.
- Controllers are invokable, `final readonly`, one action each, and only translate HTTP to a command or query.
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
- Code, comments, identifiers, commit messages and documentation are in **English**.

### PHPStan

- Level `max` with strict rules. **Do not** add `@phpstan-ignore`, baselines or `ignoreErrors` to make a check pass. Fix the code. If something truly cannot be fixed, stop and explain why.

### TypeScript (from E05)

- `strict: true`. **No `any`**: use `unknown` and narrow it. No non-null assertions (`!`) to silence the compiler.
- API types are generated from the OpenAPI schema, never written by hand.
- Server state goes through TanStack Query, never copied into local state.
- Function components and hooks only.

### Tests

- Every domain rule has a unit test. Every endpoint has a functional test.
- Test names describe behavior: `testRejectsMovementThatMakesStockNegative`.
- Unit tests in `tests/Unit/<Context>/`, functional tests in `tests/Functional/<Context>/`.

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
| `make qa` | All checks. **Must pass before proposing a commit.** |

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
- Every tenant's data is isolated: queries always go through the tenant filter. A missing tenant is an error, not "all tenants".

### Files never to modify by hand

| File | Why |
|---|---|
| `backend/config/bundles.php`, `backend/config/preload.php`, `backend/config/reference.php`, `backend/public/index.php` | Managed or generated by Symfony Flex. Running the tests regenerates `reference.php` without its `when@dev` entries: that diff is noise, discard it with `git checkout -- backend/config/reference.php`. A diff that adds a new bundle's configuration types is real and must be committed. |
| `backend/symfony.lock`, `backend/composer.lock` | Changed only through Composer |
| Committed files in `backend/migrations/` | History of the schema; add a new migration instead |
| `backend/.env` secrets | Real secrets go in `.env.local` (git-ignored) or the deployment platform |
| `LICENSE` | Legal text |

### Security

- No secrets, tokens or real customer data in the repository, fixtures or tests.
- AI features (E09) only call read-only queries and never send tenant data the user did not request.

### Accessibility (frontend, from E05)

- Target WCAG 2.2 AA: semantic HTML, labels on every input, visible focus, contrast of at least 4.5:1.
- Picking PWA is used with gloves on handheld scanners: touch targets of at least 48×48 px, every action reachable with a hardware scanner or keyboard, feedback by sound and vibration as well as color.
