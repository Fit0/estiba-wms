# 0056. An index for the latest movements

- Status: Accepted
- Date: 2026-11-15

## Context

E10-06 asked for indexes where they are needed. [ADR 0055](0055-measuring-performance-with-volume-data-and-k6.md) decided to measure first. With 500 000 movements in the ledger, `make perf-explain` showed:

- the list of the latest movements of a tenant (`GET /api/stock-movements` with no filter) read the whole ledger in parallel and sorted it: 141 ms for a page of 30;
- the movements of one location did the same: 51 ms;
- every other list query was under 15 ms, and the cost of the one query that is not (the count of the ledger) does not go away with an index.

The ledger only grows (STK-04), so these two get worse in proportion.

## Decision

One index on `stock_movements (tenant_id, occurred_at, id)`. The query orders by `occurred_at DESC, id DESC`, and PostgreSQL reads a b-tree backwards, so the same ascending index serves it and stops at the first page. After it the two queries took 2.3 ms and 3.9 ms.

The index is declared in the mapping of `StockMovement`, so `doctrine:schema:validate` agrees with the database, and the migration says which query it serves.

## What is not done, and why

- **A trigram index for the search of products.** It takes 0.4 ms with 20 000 products.
- **An index for the order of the orders.** It is a `CASE` on the priority. It would need a stored column that must be kept in step with the priority for 62 ms on page 600 of 20 000 orders.
- **A cheaper count of the ledger.** Counting is linear and the exact total is part of the contract (ADR 0026). It costs about 80 ms at 500 000 movements. If it ever matters, the way out is to page the ledger by keyset and drop the total, which is a change of the API.

## Consequences

- Each insert into the ledger maintains one index more. The ledger is written one movement at a time, by a person confirming a task, so the cost is not noticeable.
- The figures are from a laptop and say how the queries compare, not what a server would give.
