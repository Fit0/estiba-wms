# Inbound

Goods arriving: purchase orders, advance shipping notices (ASN), receiving with discrepancies, and putaway to locations.

| Layer | Holds |
|---|---|
| `Domain` | `Asn`, `Receipt`, `PutawayTask`, putaway strategies |
| `Application` | Use cases to receive goods and confirm putaway. `Application/Contract` is the only part other contexts may depend on. |
| `Infrastructure` | Doctrine XML mapping and repositories, API resources, controllers |

Model: [aggregates and invariants](../../../docs/domain/aggregates.md#inbound), [event storming](../../../docs/domain/event-storming.md#1-receiving-and-putaway), [contracts](../../../docs/domain/context-map.md#inbound).
