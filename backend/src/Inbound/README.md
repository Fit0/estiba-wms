# Inbound

Goods arriving: purchase orders, advance shipping notices (ASN), receiving with discrepancies, and putaway to locations.

| Layer | Holds |
|---|---|
| `Domain` | `Asn` and `Receipt` (E03-03), with their lines and the discrepancies worked out from them. `PutawayTask` and the putaway strategies in `Domain/Putaway` (E03-04). |
| `Application` | Use cases to register, cancel and close an ASN, to open, count, approve and close a receipt, and to confirm and split putaway tasks. The policies in `Application/Putaway` book the stock and plan the putaway when a receipt closes, and move the stock when a putaway is confirmed. They ask Catalog, Warehouse and Inventory through their contracts. `Application/Contract` is the only part other contexts may depend on. |
| `Infrastructure` | Doctrine XML mapping and repositories, API resources (`/api/asns`, `/api/receipts`, `/api/putaway-tasks`) and their state providers and processors |

How receiving works and why: [ADR 0010](../../../docs/adr/0010-receiving-against-an-asn.md). Putaway: [ADR 0011](../../../docs/adr/0011-putaway-and-stock-booked-by-event-policies.md). The strategy is chosen with the `PUTAWAY_STRATEGY` environment variable: `first_free`, `by_family` (the default) or `by_turnover`.

Model: [aggregates and invariants](../../../docs/domain/aggregates.md#inbound), [event storming](../../../docs/domain/event-storming.md#1-receiving-and-putaway), [contracts](../../../docs/domain/context-map.md#inbound).
