# Inventory

How much of each product is where. Stock is a ledger: it changes only through immutable movements, and it never goes negative.

| Layer | Holds |
|---|---|
| `Domain` | `StockItem`, `StockMovement`, `Quantity`, lot and expiry data. `StockReservation` and the allocation strategies (FEFO, FIFO) come with E04-01. |
| `Application` | The contracts other contexts use, `StockMovements` (receive, transfer) and `StockLevels`, answered on the ledger. `Application/Contract` is the only part other contexts may depend on. |
| `Infrastructure` | Doctrine XML mapping and the stock item repository, which saves each change with its movements. Read models and API resources come with E03-06. |

A stock item's lot and expiry date are part of its unique key, and are stored as `''` and `9999-12-31` rather than NULL so the unique index holds ([ADR 0011](../../../docs/adr/0011-putaway-and-stock-booked-by-event-policies.md)).

Model: [aggregates and invariants](../../../docs/domain/aggregates.md#inventory), [contracts](../../../docs/domain/context-map.md#inventory).
