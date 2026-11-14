# 0051. The ERP integration as a whole

- Status: Accepted
- Date: 2026-11-14

## Context

The integration with the ERP was decided in pieces: the simulated ERP (ADR 0046), the anti-corruption layer (ADR 0047), the scheduled import (ADR 0048), the outbox for confirmations (ADR 0049) and the panel with the contract test (ADR 0050). Each one answers a question of its own. None of them says what the whole is for, who owns what, what happens when the ERP is down, or how the warehouse could one day do without it. This record is that map. It decides little that is new: it puts the decisions side by side and says which rules hold the integration together.

The [diagrams](../architecture/erp-integration.md) show how the pieces run.

## Options considered

For who owns what:

1. **The ERP owns the customer orders and the warehouse executes them.** It is how the systems this project is modelled on divide the work, and it keeps the ERP as it is.
2. **Estiba owns the orders and the ERP is only told.** It would make the integration smaller, but Estiba would have to take over order entry, prices and customers, which is a different project.
3. **Estiba reads the ERP's database.** Nothing to build on the ERP's side. It ties the warehouse to the ERP's tables, and a real ERP rarely allows it.

For how the two talk:

1. **A channel for each direction, in the style the ERP already uses.** Files come in and SOAP goes out. The ERP is not changed.
2. **A new interface on the ERP**, such as a REST API. Cleaner, but a legacy ERP is the one that cannot be changed, which is the case this project wants to show.

## Decision

The first option of each question.

- **The ERP owns the customer order, Estiba owns what happens in the warehouse.** The ERP's order number is the order's reference in Estiba and its identity: registering a reference that exists changes nothing (OUT-01). Stock, picking, packing and dispatch are Estiba's, and the fact that a shipment left is what goes back to the ERP.
- **Orders come in as files, shipments go out over SOAP.** Every 30 seconds the `scheduler` imports the files the ERP left in an SFTP folder (ADR 0048), and every 30 seconds it sends the confirmations that are due (ADR 0049). Nothing is pushed into Estiba and nothing in the ERP is touched except through its own SOAP service.
- **One door.** Everything of the ERP's language, its CSV, Latin-1, dates day first, one-letter urgency, codes instead of faults and SOAP, lives in the `Erp` context and stops there. Two ports, `ErpOrderFiles` and `ErpGateway`, are what the rest of the context sees, and the translator is the only place where ERP words become warehouse words (ADR 0047). Outbound and Inbound do not know there is an ERP.
- **The ERP being down never stops the warehouse.** Orders wait as files, which the ERP keeps. Confirmations wait as rows in `erp_shipment_outbox`, which Estiba keeps. A dispatch takes the same time with the ERP up or down.
- **Delivery guarantees are chosen on purpose.** Import is idempotent by order. Export is at least once, and it is safe because the ERP answers code 1 ("already confirmed") to a repeat and the gateway counts it as a success. Errors say what to do, `ErpUnavailable` means try again and `ErpRejectedShipment` means do not, so the ERP's codes do not leave the gateway.
- **What failed can be seen and mended.** Every file the import dealt with has a row in `erp_import_log`, every confirmation a row in the outbox, and a supervisor reads both in the screen "Integración ERP" and can put a failed confirmation back in the queue (ADR 0050).
- **The contract is checked, not remembered.** `WsdlContractTest` fails if the WSDL the gateway is tested against differs from the ERP's own.
- **The seam is where the ERP could be replaced.** To change the ERP, the work is one adapter for each of the two ports. To move a function from the ERP to Estiba, it is a use case in the warehouse and the end of one flow. The domain does not change in either case. This is the same shape as the strangler fig pattern, and the project applies it in full later, to a legacy PHP application in E12. Nothing of that is built here, and this ADR does not claim it is.

## Consequences

- A reader can follow the integration from this record and the diagrams, and go to ADRs 0046 to 0050 for the reasons behind each part.
- The limits that the other records state one by one are the limits of the whole:
  - one tenant and one warehouse receive the ERP's orders, because the ERP does not say which, and they are settings;
  - only one `scheduler` may run, and a deployment with several would need a lock on the schedule;
  - a rejected file is not retried: the cause is fixed and the file is put back by hand;
  - the import log has no retention yet;
  - orders imported before the outbox existed are not in `erp_imported_orders`, so their shipments are not confirmed;
  - a confirmation can be lost if Redis loses the event between the dispatch and the outbox. A check that compares shipped orders with the outbox would close it and is not built;
  - the SFTP host key is not checked in development and has to be pinned before a real ERP gets a password;
  - only orders and shipment confirmations cross. Master data and the purchase side for Inbound do not.
- The simulated ERP's SOAP endpoint is ours, so it can accept what a real one would refuse. The tests use the WSDL as the contract and not the endpoint's leniency.
- Replacing the ERP is cheap where the ports are and expensive where the ERP's data model has leaked, so a change that puts ERP words in Outbound or Inbound is a change to this decision.
