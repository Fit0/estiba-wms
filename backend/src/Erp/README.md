# Erp

The anti-corruption layer between the warehouse and the ERP that owns the customer orders ([ADR 0047](../../../docs/adr/0047-an-anti-corruption-layer-for-the-erp.md)). The ERP has its own words, formats and habits. They are known here and nowhere else: what leaves this context is plain warehouse language.

| Layer | Holds |
|---|---|
| `Domain` | The ERP's model of an order (`ErpOrder`, `ErpOrderLine`, `ErpUrgency`) and the errors of talking to it |
| `Application` | The `ErpGateway` port (tell the ERP a shipment left), the `ErpOrderFiles` port (read the files it leaves), the `ErpOrderInbox` port (the folder where it leaves them), `ErpOrderTranslator`, which turns an ERP order into an `IncomingOrder` of Outbound, `ErpOrderImporter`, which does the whole import, started by the message `ImportErpOrders`, and the outbox of confirmations: `QueueShipmentConfirmation`, `ShipmentOutbox`, `ErpOutboxSender` and `RetryPolicy` |
| `Infrastructure` | `SoapErpGateway` over `ext-soap`, `CsvErpOrderFiles`, the reader of the CSV files, `SftpErpOrderInbox` over phpseclib, `DbalShipmentOutbox` and `DbalImportedOrders` (SQL), `ErpSchedule` (every 30 seconds) and the command `app:erp:import-orders` |

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

- To import an order: `CsvErpOrderFiles::read()` gives the `ErpOrder`s of a file, `ErpOrderTranslator::translate()` turns each into an `IncomingOrder` (the caller says the warehouse and the tenant), and `OrderIntake::register()` of Outbound registers it. A second time with the same number answers `AlreadyRegistered`. `OrderIntake` works for the current tenant, so a job with no signed-in user runs inside `TenantRunner::run()`.
- To import what the ERP left, as the schedule does: `ErpOrderImporter::import($tenantId, $warehouseCode)` (see below).
- To tell the ERP a shipment left: `ErpGateway::confirmShipment()`. The SOAP client is made when the first call is made, from the WSDL in `ERP_SOAP_WSDL`, so the application starts without the ERP.

## The scheduled import

[ADR 0048](../../../docs/adr/0048-a-scheduled-import-of-erp-orders.md). Every 30 seconds the `scheduler` service of Compose (`messenger:consume scheduler_erp --bus=job.bus`) sends `ImportErpOrders`. Its handler runs `ErpOrderImporter` for the tenant and the warehouse in the settings, and the importer does this with each file in the SFTP folder:

| What happens to the file | Where it goes |
|---|---|
| Its orders were registered, or were already | `processed/` |
| It is malformed, names an unknown article, or Outbound refuses its order | `rejected/`, with a warning in the log. The other files go on |
| The ERP's side or ours fails (SFTP down, database down) | It stays where it is. The run stops and the next one starts over |

Doing it twice is safe, so a run that stopped between registering and moving the file just finds the order `AlreadyRegistered` the next time and moves the file.

| Setting | Meaning | In development |
|---|---|---|
| `ERP_TENANT_ID` | The tenant that receives the orders. Empty: the import does nothing | The id printed by `make demo` |
| `ERP_WAREHOUSE_CODE` | The code of the warehouse that serves them | `ZGZ1` |
| `ERP_SFTP_HOST`, `ERP_SFTP_PORT`, `ERP_SFTP_USER`, `ERP_SFTP_PASSWORD`, `ERP_SFTP_DIRECTORY` | Where the ERP leaves its files | `erp-sftp`, `22`, `erp`, `erp-dev-password`, `outbox` |

`docker compose exec php bin/console app:erp:import-orders` runs the import once, now.

## Confirming shipments

[ADR 0049](../../../docs/adr/0049-a-database-outbox-for-erp-confirmations.md). When a shipment is dispatched, Outbound publishes `ShipmentLeft`. `QueueShipmentConfirmation` listens: if the order came from the ERP (it is in `erp_imported_orders`), it writes the confirmation into `erp_shipment_outbox`. Every 30 seconds `ErpOutboxSender` sends what is due through the `ErpGateway`:

| The ERP | The row |
|---|---|
| Took it, or already had it | `sent` |
| Could not be reached, or is not ready | `pending`, with a longer wait: 1 min, 2, 4, up to an hour; the eighth failure ends it |
| Refused it (an order it does not know, a quantity that does not match) | `failed` at once, with the reason |

A `failed` row stays in the table and is not tried again by itself; a supervisor can put it back in the queue from the panel (below). `docker compose exec php bin/console app:erp:send-confirmations` sends once, now. To see the state: `select order_reference, status, attempts, last_error from erp_shipment_outbox;`

## The integration panel

[ADR 0050](../../../docs/adr/0050-an-integration-panel-and-a-contract-test-for-the-erp.md). The importer writes a row in `erp_import_log` for every file it moves (`processed` with the number of orders, or `rejected` with the reason). Supervisors read both lists, and retry failed confirmations, in the screen "Integración ERP" (`/erp`), which uses `GET /api/erp/confirmations?status=`, `GET /api/erp/imports?result=` and `POST /api/erp/confirmations/{shipmentId}/retry`. A retry puts the row back to `pending` and the next run of the job sends it.

`tests/Unit/Erp/Infrastructure/Soap/WsdlContractTest.php` fails if `tests/Support/erp.wsdl` differs from `erp-legacy/inc/erp.wsdl` (apart from the service location), so the gateway is never tested against an old contract.

This context needs the `soap` PHP extension.
