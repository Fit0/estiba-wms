# Estiba WMS

> An open-source Warehouse Management System built with Symfony and React: receiving, putaway, inventory, picking and shipping, designed with hexagonal architecture and DDD.

[![CI](https://github.com/Fit0/estiba-wms/actions/workflows/ci.yml/badge.svg)](https://github.com/Fit0/estiba-wms/actions/workflows/ci.yml)
![Status](https://img.shields.io/badge/status-in%20development-orange)
![License](https://img.shields.io/badge/license-MIT-blue)
![PHP](https://img.shields.io/badge/PHP-8.4-777bb4)
![Symfony](https://img.shields.io/badge/Symfony-7.4%20LTS-000000)
![React](https://img.shields.io/badge/React-19-61dafb)

*Estiba* is Spanish for stowage: the craft of placing goods so they can be found, moved and shipped efficiently. This project brings that craft to software, drawing on years of real-world experience running warehouse IT and integrating WMS/ERP systems.

## Why this project

Most portfolio projects are CRUD apps. Estiba models a real business domain with real constraints:

- **Inventory as a ledger**: stock is never edited, only moved. Every unit is traceable.
- **Real warehouse flows**: ASN receiving, putaway strategies, stock allocation (FEFO/FIFO), wave picking with serpentine routes, packing and GS1 SSCC labels.
- **Legacy integration**: an anti-corruption layer talks to a simulated legacy ERP over SOAP and CSV/SFTP, using the outbox pattern for reliable delivery.
- **AI where it helps**: product classification, slotting suggestions and anomaly detection, with explainable results.
- **Operator-first UX**: an installable PWA for barcode-driven picking that keeps working offline.
- **Multi-tenant and secure by default**: JWT sign-in, three roles, and a database filter that keeps one tenant's rows away from another, even if a query forgets to ask.
- **Bilingual**: Spanish by default and English. Every API error has a stable code and is translated for each request from `Accept-Language` (see [API errors](docs/api/errors.md)).

## Architecture

The backend is organized by bounded context, each with `Domain`, `Application` and `Infrastructure` layers. The domain has no framework dependencies.

| Context | Responsibility |
|---|---|
| **Identity** | Tenants, users, roles and signing in |
| **Catalog** | Products, SKUs, GTINs, units of measure |
| **Warehouse** | Warehouses, zones and locations |
| **Inventory** | Stock ledger, movements, stock queries |
| **Inbound** | Purchase orders, ASNs, receiving, putaway |
| **Outbound** | Orders, allocation, waves, picking, packing, shipping |

The dependency rule is `Infrastructure → Application → Domain`. Contexts only reach each other through a published `Application/Contract`. Both rules are enforced by an architecture test (PHPat) that runs with `make stan` and in CI, so a violation fails the build.

Architecture decisions are recorded as ADRs in [`docs/adr`](docs/adr):

- [0001](docs/adr/0001-modular-monolith-with-hexagonal-bounded-contexts.md) Modular monolith with hexagonal bounded contexts
- [0002](docs/adr/0002-doctrine-xml-mapping-outside-the-domain.md) Doctrine XML mapping outside the domain
- [0003](docs/adr/0003-cross-context-communication-through-published-contracts.md) Cross-context communication through published contracts

See also the [system context diagram](docs/architecture/c4-context.md) and the [domain model](docs/domain/README.md): ubiquitous language, event storming, aggregates with numbered invariants, and the context map.

## Tech stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.4, Symfony 7.4 LTS, API Platform, Doctrine ORM, Messenger |
| Frontend | React 19, TypeScript, Vite, TanStack Query, React Router |
| Data | PostgreSQL, Redis |
| Real time | Mercure |
| Quality | PHPStan (max level), PHPat (architecture rules), PHP-CS-Fixer, Rector, PHPUnit, Vitest, Playwright |
| Delivery | Docker, GitHub Actions |

## Repository layout

```
estiba-wms/
├── backend/    Symfony API (hexagonal, one folder per bounded context)
├── frontend/   React + TypeScript single-page app and picking PWA
├── docs/       Architecture docs, ADRs and domain model
└── .github/    CI workflows, issue and pull request templates
```

## Roadmap

- [x] **Foundations**: Docker environment, quality tooling, CI, architecture, domain model
- [x] **Warehouse and catalog**: locations, products, API, auth, multi-tenancy
- [x] **Inventory and receiving**: stock ledger, CQRS, ASN receiving, putaway, stock read model
- [x] **Shipping**: allocation, waves, picking, packing, SSCC labels, dispatch, audit trail (`v0.1`)
- [ ] **Frontend**: React app for back-office operations
- [ ] **Picking PWA and dashboard**: barcode scanning, offline mode, KPIs
- [ ] **Legacy ERP integration**: anti-corruption layer, scheduled imports, outbox
- [ ] **AI features**: classification, slotting, anomaly detection, natural-language queries
- [ ] **Production**: deployment, observability, security, performance (`v1.0`)

## Getting started

Requirements: [Docker](https://docs.docker.com/get-docker/) with Docker Compose v2.

```bash
git clone git@github.com:Fit0/estiba-wms.git
cd estiba-wms
git config core.hooksPath .githooks
sh backend/scripts/create-env.sh   # creates backend/.env with new random secrets
docker compose up -d --wait
```

`backend/.env` is not in the repository: it holds secrets, and `backend/.env.example` is its template. `make up` runs the script for you, and it never overwrites an existing `.env`.

The first start builds the PHP image and installs Composer dependencies, so it takes a few minutes. Then check that everything is up:

```bash
curl http://localhost:8080/health
# {"status":"ok","checks":{"database":"up","redis":"up"}}
```

Besides the API, the stack runs a `worker` that handles asynchronous events, such as booking the stock of a closed receipt, with retries ([ADR 0012](docs/adr/0012-asynchronous-policies-with-a-redis-transport.md)). `make worker-logs` follows it, `make worker-restart` reloads it after a code change, and `make failed` lists what failed every retry.

The worker also keeps the stock read model in step with the ledger, so the stock screens can list stock by product and location in one query ([ADR 0013](docs/adr/0013-a-stock-read-model-fed-by-the-ledger.md)). `make stock-rebuild` builds it again from the ledger whenever it is needed.

Every endpoint under `/api` needs a token. Create a tenant with its first administrator, which asks for the password with the typing hidden, and sign in:

```bash
make tenant name="Acme Logistics" email=admin@acme.test
curl -s -X POST http://localhost:8080/api/auth/login -H 'Content-Type: application/json' \
  -d '{"email": "admin@acme.test", "password": "<the password you chose>"}'
```

The token goes in `Authorization: Bearer <token>`. See [authentication](docs/api/authentication.md) for roles, more users and the keys. The JWT key pair is created on the first start in development; `make jwt-keys` creates it again.

| Service | Address |
|---|---|
| API | http://localhost:8080 |
| PostgreSQL 17 | `localhost:5433` (user, password and database: `estiba`) |
| Redis 7.4 | internal to the Docker network |

Common commands (run `make` to list them all):

```bash
make up                      # Start the stack
make down                    # Stop it
make console c=about         # Symfony console
make composer c="require x"  # Composer
make sh                      # Shell in the PHP container
make tenant name="X" email=a@b.co  # A tenant and its admin
make demo                    # A fashion company with its warehouse and collection (dev only)
```

`make demo` loads **Marea Moda**, a fashion company that receives its collection from factories and ships it to its own stores and to franchises. It creates an admin, a supervisor and an operator (`admin@`, `supervisor@` and `operario@marea.test`, sharing the password you type), a distribution centre in Zaragoza with 6 zones and 42 locations, and 39 products: a spring-summer collection in sizes and colours. Loading it twice does nothing.

## Quality checks

| Command | What it does |
|---|---|
| `make test` | PHPUnit test suite |
| `make test-unit` | Unit tests only: no database, about a second |
| `make stan` | PHPStan at max level with strict, Symfony, Doctrine and PHPUnit rules, plus the architecture rules (PHPat) |
| `make validate` | `composer validate --strict` and `composer audit`, the same Composer checks as CI |
| `make cs` | PHP-CS-Fixer check (Symfony rules, strict types) |
| `make rector` | Rector dry run (PHP 8.4, dead code, code quality, type declarations) |
| `make fix` | Apply Rector and PHP-CS-Fixer changes |
| `make qa` | Every check above, as CI runs them |

### Continuous integration

[GitHub Actions](.github/workflows/ci.yml) runs on every pull request and every push to `main`, as five independent jobs:

| Job | Checks |
|---|---|
| Composer | `composer validate --strict`, install and `composer audit` for known vulnerabilities |
| Code style | PHP-CS-Fixer and Rector (dry run) |
| PHPStan | Static analysis at max level |
| Tests | PHPUnit against real PostgreSQL 17 and Redis 7.4 services |
| PR title | The title follows Conventional Commits, since it becomes the squash commit |

A new push to the same branch cancels the run still in progress.

Composer dependencies (`vendor/`) live in a Docker volume for speed, so always run Composer through the container. To give your IDE a copy for autocompletion, run `docker compose cp php:/app/vendor backend/`.

Ports can be changed with `HTTP_PORT` and `DATABASE_PORT` in a `.env` file next to `compose.yaml`. To debug with Xdebug, start with `XDEBUG_MODE=debug docker compose up -d`.

## Contributing

Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/). See [CONTRIBUTING.md](CONTRIBUTING.md) for the workflow and conventions.

## License

[MIT](LICENSE) © 2026 Alfonso Rios
