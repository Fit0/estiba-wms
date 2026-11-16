# 0058. Explaining why an order is not fully reserved

- Status: Accepted
- Date: 2026-11-16

## Context

Allocating an order that finds no stock is not an error (STK-11): the order stays registered or partially allocated, and the screen showed `0` in the reserved column. A person could not tell an empty warehouse from stock that is there but cannot be used. The commonest case is goods received and still at the dock: there are units, and none of them can be promised (STK-06).

The reasons are Inventory's: where each stock item is, its status and its expiry date. The location (active, type, warehouse) is Warehouse's, and the shelf life a product promises is Catalog's. Outbound knows the order and its lines.

## Options considered

1. **Return the reasons in the answer of `allocate`.** The person sees them when pressing the button, and no endpoint is added. But the reasons are not stored, so reloading the order loses them, and the allocation then has to collect the information of every skipped stock item, which it does not need to decide.
2. **Store the reason on the order line.** It survives a reload, and it goes stale: putting the goods away changes the truth and the order would not know until it is allocated again. It also adds a column to a JSON structure for what is a view of the stock.
3. **Work it out when asked, in a query.** One endpoint, `GET /api/orders/{orderId}/shortages`, asks Inventory through a new contract about each line that is short. Nothing is stored and it is always current.

## Decision

Option 3. Inventory gets a `StockAvailability` contract that, for a product and a warehouse, returns the units that cannot be promised grouped by reason (`at_dock`, `not_pickable_location`, `blocked_location`, `other_warehouse`, `not_available`, `shelf_life`, `reserved_for_others`), with a few location codes for the reasons that are about a place. It asks the same questions as the allocation, in the same order of precedence: the place first, then the status, then the shelf life. Units that the reservation of the line already holds are not counted as held by others.

Outbound's query handler asks it for each line that is not fully covered. The API returns the reason codes and the frontend writes the sentence in the language of the person, with the units and the codes, so no text is built from fragments on the server (ADR 0004).

## Consequences

- The advice follows the stock: once the goods are put away, the order stops mentioning the dock.
- Two pieces of code decide what is reservable, the allocation and this query. They agree today because both read the same rules; a change to STK-06 or STK-12 has to be made in both. The tests of the query put the stock in each situation and then allocate, so a divergence fails.
- A product with no stock at all returns no obstacles, and the screen says so.
- It reads every stock item of one product, which is a handful of rows. It is not worth caching.
