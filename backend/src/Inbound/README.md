# Inbound

Goods arriving: purchase orders, advance shipping notices (ASN), receiving with discrepancies, and putaway to locations.

| Layer | Holds |
|---|---|
| `Domain` | `PurchaseOrder`, `Asn`, `Receipt`, `PutawayTask`, putaway strategies |
| `Application` | Use cases to receive goods and confirm putaway. `Application/Contract` is the only part other contexts may depend on. |
| `Infrastructure` | Doctrine XML mapping and repositories, API resources, controllers |

The aggregates, events and published contracts are defined during domain modelling (E01-06).
