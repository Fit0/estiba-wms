# 0033. The stock screen

- Status: Accepted
- Date: 2026-10-29

## Context

E06-03 asks for the stock by product and location, with the history of movements. The product page already shows the stock of one product; this is the global query, and the first place where the ledger of ADR 0010 (movements never change) is read on screen.

## Decisions

- **One screen, two views of the same filters.** `/stock` has a tab for the lines (`GET /api/stock-items`, what is where) and one for the ledger (`GET /api/stock-movements`, how it got there, latest first). The tab, the filters, the page and its size are in the address (ADR 0031). Each tab mounts only when it is shown, so only its request is made.
- **A line links to its own history.** "See movements" opens the ledger filtered by that line (`stockItemId`), with a button to see them all again.
- **The filters the API has, with the controls people need.** The API filters by id. Product: a field that searches the catalogue with `q` (ADR 0031) and offers up to 8 results; the choice is the id. Location: a select of the active warehouse's locations. Status: a select, on the lines view only (the ledger has no status filter).
- **The lines are of the active warehouse** (ADR 0029) and wait until it is known, so the first request is already the right one. The ledger has no warehouse filter; it is filtered by location or product instead.
- **Only what the API sends.** Quantities and balances come from the server; the screen only formats them (sign on the change, thousands as the language writes them).

## Consequences

- The location select lists the first 100 locations of the warehouse, ordered by the API. A warehouse with more needs a search-by-code filter on the locations endpoint (the same gap noted in ADR 0032 for docks).
- Movements have no warehouse filter on the API. If a view per warehouse is needed, the endpoint must gain `warehouseId`.
