# Erp

The anti-corruption layer between the warehouse and the ERP that owns the customer orders ([ADR 0047](../../../docs/adr/0047-an-anti-corruption-layer-for-the-erp.md)). The ERP has its own words, formats and habits. They are known here and nowhere else: what leaves this context is plain warehouse language.

| Layer | Holds |
|---|---|
| `Domain` | The ERP's model of an order (`ErpOrder`, `ErpOrderLine`, `ErpUrgency`) and the errors of talking to it |
| `Application` | The `ErpGateway` port (tell the ERP a shipment left), the `ErpOrderFiles` port (read the files it leaves), and `ErpOrderTranslator`, which turns an ERP order into an `IncomingOrder` of Outbound |
| `Infrastructure` | `SoapErpGateway` over `ext-soap`, and `CsvErpOrderFiles`, the reader of the CSV files |

The contract with the ERP is written in [erp-legacy/README.md](../../../erp-legacy/README.md): the SOAP service, its codes, and the format of the files.

## What is translated

| The ERP says | The warehouse says |
|---|---|
| `NUMERO_PEDIDO` | the `reference` of the order. It is also its identity: an order with a reference that exists is not registered again |
| `ARTICULO` | a product of the catalogue, found by its SKU (`ProductCatalog::findBySku`) |
| `URGENCIA` `B`, `N`, `A`, `U` | priority `low`, `normal`, `high`, `urgent` |
| `FECHA_LIMITE` `08/11/2026 14:00`, Spain's time | an instant, `2026-11-08T13:00:00+00:00` |
| `PAIS`, `CP`, `POBLACION`, `DIRECCION` | the shipping address of the order |
| `ConfirmarEnvio`, code 0 | `ConfirmationOutcome::Confirmed` |
| code 1 (already confirmed) | `ConfirmationOutcome::AlreadyConfirmed`: the ERP knows, so it is a success |
| code 3 (not exported yet) | `ErpUnavailable`: try again later |
| codes 2, 4, 5, 6, 7 and any other | `ErpRejectedShipment`: sending it again will not change the answer |
| a SOAP fault of the server, a connection that fails | `ErpUnavailable` |
| a SOAP fault that blames the message | `ErpRejectedShipment` with code -1 |

A caller decides what to do from the type of the exception: retry on `ErpUnavailable`, give up and tell someone on `ErpRejectedShipment`. It never needs to know the ERP's codes.

## Using it

- To import an order: `CsvErpOrderFiles::read()` gives the `ErpOrder`s of a file, `ErpOrderTranslator::translate()` turns each into an `IncomingOrder` (the caller says the warehouse and the tenant), and `OrderIntake::register()` of Outbound registers it. A second time with the same number answers `AlreadyRegistered`. `OrderIntake` works for the current tenant, so a job with no signed-in user must set one first (E08-03).
- To tell the ERP a shipment left: `ErpGateway::confirmShipment()`. The SOAP client is made when the first call is made, from the WSDL in `ERP_SOAP_WSDL`, so the application starts without the ERP.

This context needs the `soap` PHP extension.
