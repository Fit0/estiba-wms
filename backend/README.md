# Backend

Symfony 7.4 LTS API running on PHP 8.4 with [FrankenPHP](https://frankenphp.dev).

```
backend/
├── frankenphp/   Caddyfile, PHP settings and container entrypoint
├── config/       Symfony configuration
├── public/       Web root
└── src/
    └── Shared/   Code shared by every bounded context (health check, base infrastructure)
```

Bounded contexts (`Catalog`, `Warehouse`, `Inventory`, `Inbound`, `Outbound`) are added under `src/` as the roadmap progresses, each with `Domain`, `Application` and `Infrastructure` layers.

See the root [README](../README.md) for how to run the stack.
