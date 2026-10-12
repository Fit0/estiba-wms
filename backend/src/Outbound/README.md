# Outbound

Goods leaving: customer orders, stock allocation (FEFO/FIFO), waves, picking, packing and shipping.

| Layer | Holds |
|---|---|
| `Domain` | `Order`, `Wave`, `PickList`, `Shipment` with its parcels and their SSCC |
| `Application` | Use cases to release waves, confirm picks and ship parcels. `Application/Contract` is the only part other contexts may depend on. |
| `Infrastructure` | Doctrine XML mapping and repositories, API resources, controllers, label rendering |

Allocation is not here: Inventory owns it, and Outbound asks for it through a contract (DD-3).

Model: [aggregates and invariants](../../../docs/domain/aggregates.md#outbound), event storming ([order to picking](../../../docs/domain/event-storming.md#2-order-to-picking), [packing and dispatch](../../../docs/domain/event-storming.md#3-packing-and-dispatch)), [contracts](../../../docs/domain/context-map.md#outbound).
