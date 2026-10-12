# Inventory

How much of each product is where. Stock is a ledger: it changes only through immutable movements, and it never goes negative.

| Layer | Holds |
|---|---|
| `Domain` | `StockItem`, `StockMovement`, `StockReservation`, `Quantity`, lot and expiry data, allocation strategies (FEFO, FIFO) |
| `Application` | Use cases to move stock and query it. `Application/Contract` is the only part other contexts may depend on. |
| `Infrastructure` | Doctrine XML mapping and repositories, read models, API resources, controllers |

Model: [aggregates and invariants](../../../docs/domain/aggregates.md#inventory), [contracts](../../../docs/domain/context-map.md#inventory).
