# 0011. Putaway, and stock booked by event policies

- Status: Accepted
- Date: 2026-10-21

## Context

E03-04 closes the receiving flow of the [event storming](../domain/event-storming.md#12-booking-the-stock-and-putaway): when a receipt closes, its goods enter stock at the dock and putaway tasks are planned; when an operator confirms a task, the stock moves to the location. Five questions came up:

1. How does "whenever a receipt closes" reach the code that books stock and plans tasks?
2. Inventory has a ledger in its domain but no persistence. A stock item is unique by tenant, product, location, lot, expiry date and status (DD-1), and lot and expiry are often absent.
3. How is the destination suggested, and how can the rule change?
4. "By family" and "by turnover" need facts the catalog did not have.
5. INB-11 asks for "room for the quantity". What is room?

## Options considered

1. **Policies.** The close-receipt handler calls Inventory and the planner directly; or the aggregate's events go on an event bus and policies handle them, in the same transaction or later on a queue.
2. **Unique key with absent values.** A unique index with `NULLS NOT DISTINCT` (PostgreSQL 15+, but neither Doctrine's mapping nor its schema tools know it, so the tests' schema and `schema:validate` would disagree with the migration); four partial indexes; or storing "no lot" and "no expiry" as values that cannot be real.
3. **Strategies.** One algorithm with switches, or the Strategy pattern: interchangeable classes behind one interface, chosen by name.
4. **Family and turnover.** Consolidate with the same product, reuse the storage class, or add a product family and an ABC class.
5. **Room.** Pallet positions, weight and volume, or only "empty".

## Decision

1. An **event bus** (Symfony Messenger, `event.bus`) next to the command and query buses. Handlers implement `EventHandler` and are registered by that interface. A command handler publishes its aggregate's events; the bus runs every policy **synchronously, inside the command's transaction**, so closing a receipt, booking its stock and planning its putaway commit or roll back together. An event with no handler is fine. The events carry what their policies need (who closed the receipt, the goods of a confirmed putaway), so moving a policy to an asynchronous transport in E03-05 needs no change to the domain.
2. **Sentinel values.** No lot is stored as `''` and no expiry date as `9999-12-31`, through two small DBAL types (`StockLotType`, `StockExpiryDateType`). A lot number is never empty and no product expires on that day, so neither can be confused with real data. The unique index is a plain one, Doctrine knows it, and the domain still sees `null`.
3. **Strategy pattern**, in `Inbound/Domain/Putaway`: `FirstFreeLocation`, `ByFamily` and `ByTurnover` implement `PutawayStrategy` and choose among the same candidates: the active locations of the dock's warehouse, of a type that stores the goods (quarantine for damaged goods, INB-10; reserve and pick faces otherwise), in a zone of the product's storage class, in the order of the picking route. The `PUTAWAY_STRATEGY` environment variable picks one (`by_family` by default). A strategy only suggests: the operator may choose another location with a reason (INB-13). `ByFamily` falls back to the first free location, so goods never wait for a rule.
4. A **product family** (Catalog gives one to a product, Warehouse gives zones the families they keep) and a **turnover class** A, B or C, set by hand for now and computable from shipments later (E07-04). Both live in the shared kernel, like the storage class, because two contexts use them. New and existing products start at B.
5. **Weight and volume**, from the product dimensions and what the location already holds. A location without limits always has room; strategies only suggest empty locations. Pallet positions are not counted: stock is not tracked by pallet (DD-10).

The other contexts are reached through contracts (ADR 0003): `ProductCatalog` grew the family, the turnover class and the dimensions; `LocationDirectory` lists the active locations of a warehouse with their zone's storage class and families; Inventory publishes `StockMovements` (receive, transfer) and `StockLevels`.

## Consequences

- One command can now change several aggregates through its policies. That is intended for these flows, and the architecture test keeps policies as small, final, read-only handlers.
- A policy that fails undoes the command that published the event. Once policies run on a queue (E03-05) a failure will be retried instead, and the domain events already carry what is needed for that.
- The sentinel values are visible to anyone reading `stock_items` directly. The repository and the read models must go through the DBAL types.
- Stock items are not locked while they change (DD-2). With synchronous processing two putaways of the same goods would need to collide in the same instant; the unique key still prevents duplicate stock items. Locking arrives with allocation (E04-01).
- The strategy is global for now. A per-warehouse setting can replace the environment variable without touching the strategies.
