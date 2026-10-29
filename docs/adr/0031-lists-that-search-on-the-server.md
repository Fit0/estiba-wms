# 0031. Lists that search on the server

- Status: Accepted
- Date: 2026-10-28

## Context

The catalogue (E06-01) is the first list that can hold thousands of rows and that people search by typing. The table of UI-03 is controlled and the API pages with `X-Total-Count` (ADR 0026), so the open questions were where the search runs, where its state lives and how a product is shown, given that the OpenAPI document types the lists inside a product as `unknown[]`.

## Decisions

- **The search runs on the server.** `GET /api/products` takes `q`: the products whose SKU or name contains the text, whatever the case, with `%` and `_` as plain characters. It combines with `status` and with `sku` (which is a *prefix*, and the OpenAPI text now says so). The screen never filters a page it has: a search over 10 000 products on 30 rows would lie.
- **The state of a list is in the address.** `?q=lino&status=blocked&page=2&size=100`. Going back from a product lands on the same list, a reload keeps it and a search can be sent to a colleague. The box keeps what is typed and moves the address after 300 ms without typing, so the server is asked once per word and not once per key; the address changes with `replace`, so typing does not fill the history.
- **The page on the screen stays while the next one loads** (`keepPreviousData`), so a search does not flash an empty table.
- **The lists inside a product are parsed at run time** with Zod (`catalog/schemas.ts`): GTINs, packaging levels and the stock by warehouse. A change in the shape of the API fails in a test, not as a blank cell.
- **The product page is read-only except for its status.** Data, size, tracking, codes and packaging, and the stock by warehouse and by location. Supervisors and admins block, unblock and discontinue it; blocking and discontinuing ask first in a `ConfirmDialog` (discontinuing cannot be undone), unblocking does not. Editing the data is a later task; the API already has `PATCH`, GTIN and packaging operations.

## Consequences

- A new list copies `ProductsPage`: address for state, one request per settled search, `DataTable` with its states.
- The stock by location uses the existing `GET /api/stock-items?productId=…`, paged, and the totals use `GET /api/products/{id}/stock`.
