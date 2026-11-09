# Legacy ERP (simulated)

An old ERP for Estiba to integrate with. It is made on purpose to look like the real thing: PHP 5.6, global functions, SQLite for its data, dates as `dd/mm/yyyy`, Spanish names for everything, Latin-1 files and answers made of codes. Why it is like this is in [ADR 0046](../docs/adr/0046-a-simulated-legacy-erp.md). It is a test double for the integration, not a product: nothing here is meant for production.

The ERP speaks to the WMS in two ways:

- **Orders go out as CSV files.** The ERP leaves one file per customer order in a folder, which an SFTP server shows. The WMS reads them every 30 seconds ([ADR 0048](../docs/adr/0048-a-scheduled-import-of-erp-orders.md)) and moves each one to `outbox/processed/`, or to `outbox/rejected/` if it cannot become an order.
- **Shipments come back over SOAP.** The WMS tells the ERP that an order left the warehouse, with `ConfirmarEnvio` (E08-04).

The WMS side of this contract is the `Erp` context of the backend ([ADR 0047](../docs/adr/0047-an-anti-corruption-layer-for-the-erp.md)): `CsvErpOrderFiles` reads these files and `SoapErpGateway` calls this service.

## Running it

```bash
make erp-up            # builds the image and starts the ERP and its SFTP server
make erp-order n=3     # the ERP takes three made-up orders
make erp-check         # an order, its file and its SOAP confirmation, end to end
make erp-logs          # what the ERP and the SFTP server print
```

On a new database the ERP takes 3 orders (`ERP_SEED_ORDERS`). It looks for orders that were not exported every 15 seconds (`ERP_EXPORT_INTERVAL`) and writes their files.

| What | Where |
|---|---|
| Web page with the orders | http://localhost:8081 |
| WSDL | http://localhost:8081/soap/erp.php?wsdl |
| SOAP endpoint | `http://localhost:8081/soap/erp.php` (inside Docker: `http://erp/soap/erp.php`) |
| SFTP | `localhost:2222` (inside Docker: `erp-sftp:22`), user `erp`, password `erp-dev-password` (`ERP_SFTP_PASSWORD`), folder `outbox/` |

## The order files

One file per order, called `<order number>.csv`, in the `outbox/` folder of the SFTP server.

- Encoding **ISO-8859-1** (Latin-1), separator `;`, lines end with CRLF, first line is a header.
- The file appears whole or not at all: it is written as `.<order>.csv.tmp` and then renamed, so a name that does not end in `.csv` is a file still being written and must be ignored.
- A file is written once. If it is lost the ERP does not write it again.

One row per order line:

| Column | Example | Meaning |
|---|---|---|
| `NUMERO_PEDIDO` | `P2026000001` | The ERP's order number. It is the order's identity: importing the same file twice must change nothing |
| `FECHA_PEDIDO` | `06/11/2026` | The day the order was taken, `dd/mm/yyyy` |
| `CLIENTE` | `Franquicia Madrid Salamanca` | Who the goods go to |
| `DIRECCION`, `CP`, `POBLACION` | `Calle de Serrano 61`, `28006`, `Madrid` | Delivery address |
| `PAIS` | `ES` | ISO 3166 alpha-2 country code |
| `URGENCIA` | `U` | `B` low, `N` normal, `A` high, `U` urgent |
| `FECHA_LIMITE` | `08/11/2026 14:00` | The time by which the order must leave, `dd/mm/yyyy HH:MM`, Spain's time |
| `LINEA` | `1` | Line number inside the order, from 1 |
| `ARTICULO` | `PV27-PAN-CHI-ARE-44` | The article. It is the SKU of the WMS's catalogue |
| `UNIDADES` | `4` | Units ordered |

The order data repeats on every row of the order. A field with `;`, `"` or a line break is wrapped in `"` with the inner `"` doubled.

## The SOAP service

SOAP 1.1, document/literal, namespace `urn:marea:erp:v1`. The [WSDL](inc/erp.wsdl) is the reference. The ERP **never uses a SOAP fault for a business problem**: it answers `Codigo` and `Mensaje`, as old ERPs do. A fault is only for a message it cannot read.

### `ConfirmarEnvio`

Tells the ERP that an order has left the warehouse.

| Field | Meaning |
|---|---|
| `NumeroPedido` | The ERP's order number |
| `FechaEnvio` | The day it left, `dd/mm/yyyy` |
| `Transportista` | The carrier. Required |
| `Seguimiento` | The tracking number. Optional |
| `Bultos` | How many parcels. At least 1 |
| `Lineas/Linea` | One for each article that shipped: `Articulo` and `Unidades`. At least one. An article of the order that is not mentioned is taken as not shipped; fewer units than ordered is a partial shipment |

The answer has `Codigo` and `Mensaje`:

| `Codigo` | Meaning | Changes anything |
|---|---|---|
| 0 | Shipment recorded | Yes |
| 1 | The order was already confirmed | No. A resend after a lost answer lands here, so the WMS should count it as delivered |
| 2 | There is no such order | No |
| 3 | The order has not been exported yet | No |
| 4 | An article is not in the order | No |
| 5 | Units below zero or above the units ordered | No |
| 6 | The date is not a real `dd/mm/yyyy` | No |
| 7 | The carrier, the parcels or the lines are missing | No |

### `ConsultarPedido`

Asks for the state of an order. The answer has `Codigo` (0 found, 2 not found), `Mensaje`, and, when found, `Estado` (`PENDIENTE`, `EXPORTADO` or `ENVIADO`) with `ExportadoEn` and `EnviadoEn` when they exist, as `dd/mm/yyyy HH:MM`.

## How it is made

```
www/      what Apache serves: the orders page and the SOAP endpoint
inc/      the code: orders, the CSV export, the SOAP service and its WSDL
bin/      scripts: create the database, export, take an order, check everything
```

The code stays out of the web folder and the SQLite file and the CSV files live in Docker volumes. `bin/comprobar.php` is the check that `make erp-check` runs; it needs no extra tools, so it also runs with any PHP: `ERP_DATA_DIR=/tmp/erp ERP_OUTBOX_DIR=/tmp/out php -S 127.0.0.1:8099 -t www` and then `php bin/comprobar.php http://127.0.0.1:8099`.

The ERP's articles (`inc/catalogo.php`) are the ones of the Marea Moda demo of the WMS (`backend/src/Demo/Fashion`). If the demo catalogue changes, change them too.
