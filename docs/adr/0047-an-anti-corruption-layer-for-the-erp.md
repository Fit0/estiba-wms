# 0047. An anti-corruption layer for the ERP

- Status: Accepted
- Date: 2026-11-06

## Context

The simulated ERP (ADR 0046) leaves its customer orders as CSV files and takes the confirmation of a shipment over SOAP. Its model is not ours: articles are SKUs, urgency is a letter, a date is written day first and the due time is Spain's clock, an answer is a code, and a file is Latin-1. If any of this reaches the domain, the domain starts to speak the ERP's language, and the day the ERP changes, or a second one arrives, the warehouse has to change with it.

Two things must cross the border. Orders come in, and the confirmation of a shipment goes out. The question is where the translation lives and how the rest of the backend sees it.

## Options considered

1. **Inside Outbound.** Few files. Outbound would learn SOAP, the ERP's codes and its CSV, which are not its business, and Inbound would need the same again for the purchase side.
2. **A bounded context of its own, `Erp`.** It depends on the contracts of Catalog and Outbound and nothing depends on it. The cost is one more context, with its own layers and a line in the architecture test.
3. **A generic integration library.** More than one ERP is not on the table, so it would be built for a need that does not exist.

For the SOAP client: **`SoapClient` of `ext-soap`**, which reads the real WSDL and builds the request from it, or **XML written by hand**, which needs no extension but checks nothing against the contract. For how an order enters Outbound: **a new `OrderIntake` contract**, or **Erp dispatching Outbound's `RegisterOrder` command**, which would reach into Outbound's `Application` and break ADR 0003.

## Decision

A bounded context `Erp` (option 2). It may depend on `Catalog` and `Outbound`, through their `Application/Contract` only.

- **Two ports in `Application`.** `ErpGateway` tells the ERP a shipment left. `ErpOrderFiles` reads the files it leaves. Their implementations, `SoapErpGateway` and `CsvErpOrderFiles`, are the only code that knows SOAP, CSV or Latin-1.
- **The ERP's model stays in `Erp/Domain`.** `ErpOrder` keeps the ERP's words as they are, with the letter of urgency as `ErpUrgency`. `ErpOrderTranslator` is the one place where they become warehouse words: the article becomes a product by its SKU, the letter a priority, the due time an instant. What it returns, an `IncomingOrder`, knows nothing of the ERP.
- **Outbound publishes `OrderIntake`**, with `IncomingOrder` and `IntakeResult`, and implements it with the same `RegisterOrder` command the API uses, so an order from the ERP obeys the same rules. An order whose reference exists is not an error: it answers `AlreadyRegistered`, which is what makes importing the same file twice safe (OUT-01).
- **Catalog publishes `findBySku`** in `ProductCatalog`: the ERP names products by SKU, and a SKU is what the tenant already calls them.
- **`ext-soap` and the real WSDL** (option 1 of the client). The client is made on the first call, not at start, because making it reads the WSDL from the ERP, and an ERP that is down must not stop the application.
- **Errors tell what to do, not what the ERP said.** `ErpUnavailable` means try again, `ErpRejectedShipment` means do not. The outbox that sends confirmations (E08-04) chooses from the type. A second confirmation of the same order, which the ERP answers with code 1, is a success: it is what a retry after a lost answer meets.
- **Tests do not call anyone.** The gateway is tested with a `SoapClient` that reads a copy of the WSDL (`tests/Support/erp.wsdl`) and answers with a prepared XML, so a request that the contract would not accept fails too. The reader is tested with a file that the simulated ERP wrote (`tests/Support/erp/P2026000004.csv`).

The cut-off of an order is now kept in UTC when the order is registered. The database stores the clock of an instant and not its time zone, so a cut-off of 14:00 in Madrid used to come back as 14:00 UTC, an hour late. It was found because the ERP gives its times in Spain's clock; it was also true of the API for any offset other than zero.

## Consequences

- The domain of Outbound and Inbound never sees an ERP. Replacing the ERP, or the way to talk to it, means writing another adapter for the two ports.
- The `soap` PHP extension is now required. It is in the Dockerfile and in the CI, and `composer.json` does not declare it yet.
- A copy of the WSDL lives in the tests of the backend. If `erp-legacy/inc/erp.wsdl` changes, the copy must change too. The contract tests of E08-05 are the place to make that mistake impossible.
- `OrderIntake` registers for the current tenant, as every use case does. A scheduled job has no signed-in user, so E08-03 has to give it the tenant and the warehouse the ERP's orders go to. The ERP does not say which one.
- Only orders and shipment confirmations cross. Master data, such as products, and the purchase side for Inbound, are not in this context yet.
