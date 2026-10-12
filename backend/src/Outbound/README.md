# Outbound

Goods leaving: customer orders, stock allocation (FEFO/FIFO), waves, picking, packing and shipping.

| Layer | Holds |
|---|---|
| `Domain` | `Order`, `Allocation`, `Wave`, `PickTask`, `Parcel` with its SSCC |
| `Application` | Use cases to release waves, confirm picks and ship parcels. `Application/Contract` is the only part other contexts may depend on. |
| `Infrastructure` | Doctrine XML mapping and repositories, API resources, controllers, label rendering |

The aggregates, events and published contracts are defined during domain modelling (E01-06).
