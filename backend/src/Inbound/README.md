# Inbound

Goods arriving: purchase orders, advance shipping notices (ASN), receiving with discrepancies, and putaway to locations.

| Layer | Holds |
|---|---|
| `Domain` | `Asn` and `Receipt` (E03-03), with their lines and the discrepancies worked out from them. `PutawayTask` and putaway strategies come in E03-04. |
| `Application` | Use cases to register, cancel and close an ASN, and to open, count, approve and close a receipt. They ask Catalog and Warehouse through their contracts. `Application/Contract` is the only part other contexts may depend on. |
| `Infrastructure` | Doctrine XML mapping and repositories, API resources (`/api/asns`, `/api/receipts`) and their state providers and processors |

How receiving works and why: [ADR 0010](../../../docs/adr/0010-receiving-against-an-asn.md).

Model: [aggregates and invariants](../../../docs/domain/aggregates.md#inbound), [event storming](../../../docs/domain/event-storming.md#1-receiving-and-putaway), [contracts](../../../docs/domain/context-map.md#inbound).
