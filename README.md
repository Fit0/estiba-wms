# Estiba WMS

> An open-source Warehouse Management System built with Symfony and React: receiving, putaway, inventory, picking and shipping, designed with hexagonal architecture and DDD.

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

## Architecture

The backend is organized by bounded context, each with `Domain`, `Application` and `Infrastructure` layers. The domain has no framework dependencies.

| Context | Responsibility |
|---|---|
| **Catalog** | Products, SKUs, GTINs, units of measure |
| **Warehouse** | Warehouses, zones and locations |
| **Inventory** | Stock ledger, movements, stock queries |
| **Inbound** | Purchase orders, ASNs, receiving, putaway |
| **Outbound** | Orders, allocation, waves, picking, packing, shipping |

Architecture decisions are recorded as ADRs in [`docs/adr`](docs/adr).

## Tech stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.4, Symfony 7.4 LTS, API Platform, Doctrine ORM, Messenger |
| Frontend | React 19, TypeScript, Vite, TanStack Query, React Router |
| Data | PostgreSQL, Redis |
| Real time | Mercure |
| Quality | PHPStan (max level), PHP-CS-Fixer, Rector, PHPUnit, Vitest, Playwright |
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

- [ ] **Foundations**: Docker environment, quality tooling, CI, architecture
- [ ] **Warehouse and catalog**: locations, products, API, auth, multi-tenancy
- [ ] **Inventory and receiving**: stock ledger, CQRS, ASN receiving, putaway
- [ ] **Shipping**: allocation, waves, picking, packing, SSCC labels (`v0.1`)
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
docker compose up -d --wait
```

The first start builds the PHP image and installs Composer dependencies, so it takes a few minutes. Then check that everything is up:

```bash
curl http://localhost:8080/health
# {"status":"ok","checks":{"database":"up","redis":"up"}}
```

| Service | Address |
|---|---|
| API | http://localhost:8080 |
| PostgreSQL 17 | `localhost:5433` (user, password and database: `estiba`) |
| Redis 7.4 | internal to the Docker network |

Common commands:

```bash
docker compose exec php bin/console about    # Symfony console
docker compose exec php composer <command>   # Composer
docker compose logs -f php                   # Follow PHP logs
docker compose down                          # Stop everything
```

Composer dependencies (`vendor/`) live in a Docker volume for speed, so always run Composer through the container. To give your IDE a copy for autocompletion, run `docker compose cp php:/app/vendor backend/`.

Ports can be changed with `HTTP_PORT` and `DATABASE_PORT` in a `.env` file next to `compose.yaml`. To debug with Xdebug, start with `XDEBUG_MODE=debug docker compose up -d`.

## Contributing

Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/). See [CONTRIBUTING.md](CONTRIBUTING.md) for the workflow and conventions.

## License

[MIT](LICENSE) © 2026 Alfonso Rios
