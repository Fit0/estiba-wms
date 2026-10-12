# Domain model

How a warehouse works, written down before it is coded. Everything in `backend/src/*/Domain` follows these documents. When the code and the documents disagree, fix whichever one is wrong in the same pull request.

| Document | What it answers |
|---|---|
| [Ubiquitous language](ubiquitous-language.md) | What each term means, with the Spanish warehouse term next to it |
| [Event storming](event-storming.md) | What happens, in order: commands, events and policies for receiving, order to shipment and stock corrections |
| [Aggregates and invariants](aggregates.md) | What each context owns and which rules can never be broken |
| [Context map](context-map.md) | Who depends on whom, and which contracts each context publishes |

## Scope of version 1.0

**In scope**

- Receiving against an advance shipping notice (ASN), with discrepancies
- Putaway with suggested locations
- Stock as an immutable ledger, with lot and expiry tracking
- Allocation with FEFO and FIFO
- Wave picking with one pick list per operator, following a serpentine route
- Packing into parcels with GS1 SSCC labels, and dispatch
- Stock adjustments with a reason
- Integration with a legacy ERP (orders in, confirmations out)

**Out of scope** (possible later, deliberately not designed now)

- Serial numbers and pallet tracking by license plate
- Returns, kitting and cross-docking
- Cycle count sessions (adjustments cover the corrections)
- Replenishment of pick faces
- Transfers between warehouses
- Carrier integration and rate shopping

## Design decisions

Decisions that shape the model and could reasonably have gone another way. The ones with lasting architectural impact become ADRs when they are implemented.

| # | Decision | Alternative rejected |
|---|---|---|
| DD-1 | `StockItem` is one bucket per product, location, lot, expiry date and stock status. Movements are separate append-only rows. | One aggregate per product and warehouse. Every picker of a popular item would contend for the same lock. |
| DD-2 | Inside Inventory, transfer, allocate, consume and release each change several `StockItem` aggregates and a `StockReservation` in one transaction, always locking them in a fixed order. It is the one place where a transaction spans several aggregates. | A virtual in-transit location, which adds states and a cleanup process. |
| DD-3 | Inventory owns allocation. Outbound asks for stock through a contract and receives the reservation. | Outbound choosing stock itself, which would duplicate knowledge of lots, expiry and locations. |
| DD-4 | Locations are never deleted, only blocked. History keeps pointing at them. | Deleting empty locations, which makes Warehouse depend on Inventory to check that they are empty. |
| DD-5 | Quantities are integers in the product's base unit. Packaging levels convert at the edges (import, screens, labels). | Decimal quantities and mixed units inside the domain. |
| DD-6 | Receiving needs an ASN. Goods that arrive without one are handled as a supplier incident outside the system. | Blind receiving, which creates a placeholder ASN and adds cases and rules. |
| DD-7 | Dependencies between contexts form a directed graph with no cycles: Catalog and Warehouse, then Inventory, then Inbound and Outbound. The architecture test enforces it. | Contexts calling each other in both directions. |
| DD-8 | Stock is allocated when the order is registered, so a shortage shows up at once and can be reported or left as a backorder. | Allocating when the wave is released, which keeps stock free longer but finds shortages with the operator already in the aisle. |
| DD-9 | A released wave becomes one pick list per operator and zone, walked in a serpentine route. Each line goes into its order's container. | Batch picking with a put wall, which suits many small orders but adds a step and a new aggregate. |
| DD-10 | Stock is tracked by lot and expiry date. | Tracking by pallet license plate, which changes the stock and receiving models substantially. |
| DD-11 | The interface is bilingual (Spanish by default, English). Domain exceptions carry a stable error code and parameters, never a sentence. See [ADR 0004](../adr/0004-bilingual-interface-spanish-by-default.md). | Spanish only, which hides the demo from anyone who does not read it. |

DD-6 and DD-8 to DD-10 were confirmed by the author, who works with warehouse systems. They can be revisited with a new ADR if the product grows.
