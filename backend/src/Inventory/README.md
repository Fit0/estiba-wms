# Inventory

How much of each product is where. Stock is a ledger: it changes only through immutable movements, and it never goes negative.

| Layer | Holds |
|---|---|
| `Domain` | `StockItem`, `StockMovement`, `Quantity`, lot and expiry data |
| `Application` | Use cases to move stock and query it. `Application/Contract` is the only part other contexts may depend on. |
| `Infrastructure` | Doctrine XML mapping and repositories, read models, API resources, controllers |

The aggregates, events and published contracts are defined during domain modelling (E01-06).
