# 0005. Persisting value objects, zones and the unit of work

- Status: Accepted
- Date: 2026-10-18

## Context

ADR 0002 keeps the Doctrine mapping in XML, outside the domain. Three questions came up when the Warehouse context was mapped:

1. How do value objects (`LocationCode`, `WarehouseCode`, identifiers) reach a column without Doctrine attributes on them?
2. How is `Warehouse::$zones` stored? Doctrine fills a relation with its own collection class, which the domain must not import, so a plain `list<Zone>` cannot be a mapped relation.
3. Who flushes: the repository's `save()` or the caller?

## Options considered

1. **Value objects.** Embeddables (one column per property) or a custom DBAL type per value object. Embeddables need public mutable-looking structure and cannot run the validation of `fromString()` when loading.
2. **Zones.** A separate `zones` table with a collection in the aggregate (imports Doctrine), a second persistence model with mappers (ADR 0002 option 3), or a JSON column converted by a custom type.
3. **Flush.** `save()` persists and flushes, or `save()` only stages the aggregate and the caller flushes.

## Decision

1. One small custom DBAL type per value object, in `Infrastructure/Persistence/Doctrine/Type/`, built on `IdentifierType` and `StringValueObjectType` in `Shared`. Loading goes through `fromString()`, so a value read from the database is validated like a new one. `LocationCapacity`, which has three independent columns, is a Doctrine embeddable mapped in XML.
2. Zones are stored as a JSON document in the `warehouses` row, converted by `ZonesType`. A warehouse has a handful of zones that are always read and written with it, and rules WH-02 and WH-05 are enforced in the aggregate. Nothing queries by zone inside the JSON; locations refer to a zone by `zone_id` with no foreign key.
3. `save()` only stages the aggregate with the unit of work. The caller that owns the transaction flushes (the transaction middleware of the command bus, from E03), so several aggregates can change atomically.

Unique constraints back the registry checks against races: `(tenant_id, code)` on `warehouses` (WH-01) and `(warehouse_id, code)` on `locations` (WH-03).

## Consequences

- The domain stays free of Doctrine, and invalid data in the database fails loudly when read.
- Each new value object that is persisted needs a small type class and an entry in `doctrine.yaml`.
- Zones cannot be filtered or joined in SQL without JSON operators. If that becomes a need, zones can move to their own table in a new migration, with the same domain API.
- The command bus flushes at the end of every command ([ADR 0009](0009-command-and-query-buses-with-symfony-messenger.md)). Outside a command, such as a demo loader or a test, whoever calls a repository must call `flush()`.
