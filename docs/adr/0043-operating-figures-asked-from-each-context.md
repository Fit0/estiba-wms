# 0043. Operating figures asked from each context

- Status: Accepted
- Date: 2026-11-03

## Context

The dashboard (E07-05) needs a handful of figures a supervisor looks at to run the warehouse: orders open, registered and shipped, how fast picking goes, how much of what was asked was not found, the stock by status and how many storage locations hold stock. They come from three contexts: Outbound (orders, pick lists), Inventory (stock) and Warehouse (the locations that exist).

The rules of this repository say a query that shows data of several contexts reads a projection, and that no context joins another one's tables (ADR 0003, ADR 0013). A projection fed by policies would be a fourth copy of the data for six numbers.

## Options considered

1. **One endpoint per context** (`/api/orders/kpis`, `/api/stock/kpis`…). Each context stays whole, but the dashboard makes three requests, two of them for numbers that only mean something together (occupancy needs the locations of Warehouse and the stock of Inventory).
2. **A projection** filled by policies from every event. It is the pattern of the stock overview, and the right one when a figure needs history that no table keeps. Here every figure can be worked out from a table that already exists, so the policies would be code that only duplicates.
3. **Plain SQL over a shared database**, joining the tables of the three contexts. The quickest, and the one ADR 0003 forbids.
4. **A new context, `Analytics`, that asks each context for its own figures through a contract** and adds them up. Each context computes its figures with plain SQL over its own tables; Analytics owns no table.

## Decision

Option 4. `Analytics` depends on `OutboundKpis`, `StockKpis` and `LocationCensus`, which live in the `Application/Contract` of Outbound, Inventory and Warehouse, and nothing depends on it. Its only use case, `GetKpis`, builds a `KpiReport` and the endpoint `GET /api/kpis` (supervisors and admins, like the audit trail) shows it.

- **Computed when asked.** No snapshot table, no job. The queries are aggregates over indexed tables (`tenant_id`, status, a date), and the stops of a pick list, which are a JSON column, are unfolded with `json_array_elements`. If a warehouse outgrows this, the contracts stay and an implementation behind them can read a projection instead.
- **A period for what happened, a picture for what is.** Orders registered and shipped, and all of picking, count what happened between `from` (included) and `until` (not included); by default the last seven days, at most 366. Orders open, stock and locations are a picture of now: asking for last March does not tell what the stock was then.
- **Picking is measured on the pick lists completed in the period.** The time of a list is the time between its start and its completion; lines per hour is the stops confirmed over that time. It is the time of the lists, not of the operators: pauses are in it.
- **A short pick is the units not found over the units asked** (those picked and those not found), in percent.
- **Occupancy is the share of active storage locations (reserve and pick face) that hold at least one unit.** Docks, staging, packing and quarantine are not storage. Warehouse gives the ids of the locations and Inventory counts how many of them hold stock, in chunks of 5,000 ids; that is fine for thousands of locations and the first thing to rethink for a hundred thousand.
- **A ratio with nothing to divide by is left out**, not sent as zero (the API leaves out what is null, ADR 0026): no completed list is not an infinitely slow picking.
- **The bounds are compared with their microseconds.** The columns keep whole seconds, so an event of the second that is passing now would equal an `until` of "now" and fall out of the period.

## Consequences

- One request gives the dashboard everything, and the contexts keep their tables to themselves.
- Adding a figure means adding it to the contract of the context that owns the data, and then to the report.
- The figures of a request are read one query after another, not in one snapshot: a stock movement between two queries can make them differ by a unit. For a dashboard that refreshes every few seconds that is acceptable.
- The stock figures read the stock overview, so they are as fresh as that projection (it follows the movements, ADR 0013).
- Damaged stock is stock marked as such that is still in the warehouse; what was written off the ledger is not in it.
