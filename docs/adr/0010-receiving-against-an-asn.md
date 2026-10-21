# 0010. Receiving goods against an ASN

- Status: Accepted
- Date: 2026-10-21

## Context

E03-03 adds receiving: a supplier announces a delivery (the ASN), operators count what arrives at a dock (the receipt), and the differences are recorded as discrepancies. The model in [aggregates.md](../domain/aggregates.md#inbound) names two aggregates, `Asn` and `Receipt`, and leaves four questions open:

1. A delivery can arrive in several trucks. How does a second receipt know what is still to come, and who keeps the running total?
2. Where do discrepancies live: stored rows, or something worked out from the counts?
3. Receiving needs facts that belong to other contexts: whether a product exists, is active and is tracked by lot and expiry date (Catalog), and whether a location is an active dock (Warehouse).
4. Who may do what. Until now every change needed a supervisor, but counting at the dock is the operators' job.

## Options considered

1. **Running total.**
   a. Each receipt knows only its own counts, and a query adds up the receipts of an ASN. The ASN cannot change status without asking the receipts.
   b. Receipts are opened and closed through the ASN, which adds each closed receipt to its lines. Two aggregates change in one transaction.
   c. A policy updates the ASN after `ReceiptClosed`, eventually. Simple aggregates, but a second receipt opened before the policy runs expects the wrong quantities.
2. **Discrepancies.** Stored rows, written as items are counted, or derived from the expected items and the counts whenever they are asked for.
3. **Other contexts.** Call their repositories (forbidden by ADR 0003), copy their data into Inbound, or call published contracts.
4. **Roles.** Open the dock work to operators route by route, or move the role checks into the handlers.

## Decision

1. Option b. `Asn::openReceipt()` creates the receipt, copying the units of each line that are still to come, and remembers it as the open receipt. `Asn::closeReceipt()` closes the receipt and adds what it counted to the lines, so the ASN moves to partially received or received by itself. Both aggregates belong to Inbound and the command bus commits them in one transaction ([ADR 0009](0009-command-and-query-buses-with-symfony-messenger.md)). To keep the total right, an ASN has one open receipt at a time (INB-16).
2. Derived. The receipt keeps what it expected and its lines, and `Receipt::discrepancies()` works out shortages, excesses, unexpected products and damaged units. A stored list could disagree with the counts; a derived one cannot. `DiscrepancyDetected` is still raised as items are counted, for anyone who wants to react.
3. Published contracts: `ProductCatalog` in Catalog and `LocationDirectory` in Warehouse, each returning a snapshot ([ADR 0003](0003-cross-context-communication-through-published-contracts.md)). The application layer turns a product snapshot into a `ReceivableProduct`, and the domain decides with it (INB-01, INB-07).
4. Route by route, in `access_control`: operators may open a receipt, count items and close it. Approving an over-receipt, and registering, cancelling or closing an ASN, stay with supervisors (INB-08).

Lines of both aggregates are stored as JSON in their rows, like the zones of a warehouse ([ADR 0005](0005-persisting-value-objects-and-aggregates.md)). The aggregates replace their lists instead of changing them, so Doctrine notices every change.

## Consequences

- The ASN status is always consistent with its closed receipts, at the price of one transaction that spans two aggregates of the same context. Two receipts of the same ASN cannot be counted in parallel. If that becomes a need, each receipt can expect its own share of the lines.
- A receipt that was opened by mistake has to be closed (empty, as a shortage) to free its ASN. A cancel operation can come later without changing the model.
- Receiving more of a product that is already over its expected quantity withdraws an earlier approval, so a supervisor never approves units they did not see.
- Stock is not booked yet. `ReceiptClosed` carries the receipt, and booking the stock (damaged units in quarantine, INB-10) and planning the putaway follow from it in E03-04.
