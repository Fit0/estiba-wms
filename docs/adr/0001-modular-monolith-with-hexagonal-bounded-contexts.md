# 0001. Modular monolith with hexagonal bounded contexts

- Status: Accepted
- Date: 2026-10-12

## Context

Estiba covers several business areas that change for different reasons: what the warehouse handles (catalog), where it can store it (warehouse layout), how much there is (inventory), what arrives (inbound) and what leaves (outbound).

Symfony's default layout groups code by technical type (`Controller/`, `Entity/`, `Repository/`). That layout lets business rules end up inside framework classes, and it says nothing about which business areas depend on each other.

The project is built and operated by one person, and it must be easy to run, test and deploy.

## Options considered

1. **Symfony default layout.** Least ceremony, but the domain depends on Doctrine and Symfony, and nothing prevents any class from using any other.
2. **Modular monolith with bounded contexts and hexagonal layers.** One deployable application, split into contexts that each have their own `Domain`, `Application` and `Infrastructure` layers.
3. **Microservices, one per context.** Strongest isolation, but it adds network calls, distributed transactions and deployment work that bring no benefit at this size.

## Decision

Option 2. The backend is a single Symfony application organized by bounded context under `backend/src/`:

```
src/
├── Shared/                  Code no single context owns
└── <Context>/               Catalog, Warehouse, Inventory, Inbound, Outbound
    ├── Domain/              Entities, value objects, domain events, repository interfaces
    ├── Application/         Use cases (commands, queries, handlers)
    │   └── Contract/        The only part other contexts may depend on (see ADR 0003)
    └── Infrastructure/      Doctrine, HTTP controllers, API resources, external adapters
```

The dependency rule is `Infrastructure → Application → Domain`. The domain contains plain PHP and no framework code.

The rule is enforced, not only documented: [`tests/Architecture/ArchitectureTest.php`](../../backend/tests/Architecture/ArchitectureTest.php) uses PHPat through PHPStan, so a violation fails `make stan` and the CI.

## Consequences

- Business rules can be tested with plain PHPUnit, with no kernel and no database.
- More folders and more mapping code than the default layout. Each use case needs a handler, and each aggregate needs an XML mapping (see ADR 0002).
- A context can later be extracted into its own service, because the only coupling is its published contract.
- Adding a bounded context means adding it to `ArchitectureTest::CONTEXTS`.
