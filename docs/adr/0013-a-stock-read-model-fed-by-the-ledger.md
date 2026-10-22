# 0013. A stock read model fed by the ledger

- Status: Accepted
- Date: 2026-10-22

## Context

E03-06 asks for stock by product and by location. The answer people want on a screen is not what the ledger holds. A stock item knows a product id and a location id. A person wants the SKU, the product name, the location code and the warehouse. Those live in Catalog and Warehouse, and ADR 0003 forbids joining their tables: "queries that combine data from several contexts need a dedicated read model".

The frontend will also need the history of a stock item or a product (E06-03), and a total per product and warehouse.

Four questions came up:

1. Where do the SKU and the codes come from at read time?
2. How does the read model stay in step with the ledger, given that stock changes inside policies the worker runs (ADR 0012)?
3. What happens when a message is delivered twice, or two arrive out of order?
4. What if the read model is lost or goes wrong?

## Options considered

1. **Query the ledger and enrich it.** Read `stock_items` and ask `ProductCatalog` and `LocationDirectory` for every page. It is always exact and adds no table. But a list of 30 stock items means 60 contract calls, and filtering or sorting by SKU or warehouse is impossible in SQL.
2. **A projection fed by events.** Every movement publishes `StockMovementRecorded`. A policy copies the stock item, with its SKU, name and codes, into a table of its own. Lists are a single indexed query. The cost is eventual consistency and data copied from other contexts.
3. **A database view or materialized view joining the contexts' tables.** It is fast to write, but it is the join ADR 0003 rules out, and it hides the dependency from the architecture test.

For the projection, the policy can apply the change of each event (`on_hand + change`) or rewrite the row from the stock item as it is now.

## Decision

Option 2: a **projection** in Inventory, the `stock_overview` table, one row per stock item.

- `LedgerStockMovements` publishes the events of every stock item it saves. They leave after the caller commits (ADR 0012). `StockMovementRecorded` is routed to `async` and now carries the tenant, since the worker has no signed-in user.
- `ProjectStockOverview` loads the stock item and gives it to `StockOverviewProjector`. The projector asks the contracts for the product and the location and **writes the whole row** with an upsert. It never adds a change to what the row held. Running it twice, or for two movements in the wrong order, leaves the right row. So this policy is idempotent without a mark of its own.
- The contracts grew what the read model shows: `ProductSnapshot::name` and `LocationSnapshot::warehouseCode`.
- The table is written and read with **plain SQL** (DBAL) in `Infrastructure/Persistence/ReadModel`. A read model has no behaviour to protect, so loading entities would only add cost. It is still mapped, as `StockOverviewRecord`, so the migrations, `schema:validate` and the test database know it. Like `product_gtins` in Catalog, the mapping is a persistence detail. "No lot" and "no expiry date" are `NULL` here. The sentinel values of ADR 0011 exist to protect a unique key, and this table has none to protect.
- The movement history reads the ledger itself (`stock_movements`), joined to `stock_overview` for the codes. Both tables belong to Inventory, so the join stays inside the context.
- `app:stock-overview:rebuild` empties the table and projects every stock item again in one transaction. Readers see the old rows until it commits. This is what the ledger buys: the read model is disposable.
- Endpoints, all read-only and open to operators:
  - `GET /api/stock-items` (filters `productId`, `locationId`, `warehouseId`, `status`)
  - `GET /api/stock-items/{id}`
  - `GET /api/products/{productId}/stock`, with totals by status and by warehouse
  - `GET /api/stock-movements` (filters `stockItemId`, `productId`, `locationId`), newest first

## Consequences

- Lists and totals are one query each, filtered and sorted in SQL, whatever the size of a page.
- The read model lags the ledger by the moment the worker takes. In the tests the transport is synchronous, so it is up to date when the request answers. A client that has just put stock away may not see it in the list for a moment.
- The SKU, the name and the codes are copies. Renaming a product or recoding a location does not reach existing rows until that stock item moves again or the read model is rebuilt. Integration events from Catalog and Warehouse (`ProductRenamed` and the like) can close that gap when a screen needs it.
- After deploying this change, run `app:stock-overview:rebuild` once: the migration creates the table empty, because SQL may not copy the data from the other contexts.
- `available` is the units in available status until reservations arrive (E04-01). Then the read model gains a `reserved` column and `available` leaves the reserved units out.
- Two workers projecting the same stock item at the same moment could, in theory, write an older state last. There is one worker for now; the next movement or a rebuild corrects it. If workers are scaled out, the row should carry the ledger position it reflects and refuse an older one.
