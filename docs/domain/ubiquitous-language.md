# Ubiquitous language

One term, one meaning, in code, tests, screens and documents. Code is in English. The interface is bilingual ([ADR 0004](../adr/0004-bilingual-interface-spanish-by-default.md)): the Spanish column is the term warehouse staff actually use and the one the Spanish interface shows, and the English term is the one the English interface shows.

## Access

| Term | Spanish | Meaning | Context |
|---|---|---|---|
| User | Usuario | A person who signs in. It belongs to one tenant for its whole life. | Identity |
| Role | Rol | What a user may do: administrator (`admin`), supervisor or operator (`operator`, "operario" on the floor). Each includes the ones below it. | Identity |
| Sign in | Iniciar sesión | Proving who you are with an email and a password, to get a token. | Identity |
| Token | Token de acceso | What a sign-in returns. It lasts one hour and is sent with every request. | Identity |
| Disabled user | Usuario deshabilitado | A user that cannot sign in, nor use a token it already holds. | Identity |

## Master data

| Term | Spanish | Meaning | Context |
|---|---|---|---|
| Tenant | Cliente, inquilino | The company whose goods and data the system holds. Every aggregate belongs to one tenant, and no query crosses tenants. | Shared |
| Warehouse | Almacén | A physical site with its own layout of locations. | Warehouse |
| Zone | Zona | A named area of a warehouse with one storage class and one picking sequence, for example "chilled" or "fast movers". It can keep some product families. | Warehouse |
| Location | Ubicación | The smallest place where stock can be put, identified by a location code. | Warehouse |
| Location code | Código de ubicación | `aisle-module-level`, for example `A-03-02`. Unique within a warehouse. | Warehouse |
| Location type | Tipo de ubicación | Reserve (bulk storage), pick face (picking position), dock (receiving or shipping), staging (waiting area), packing, quarantine. | Warehouse |
| Storage class | Clase de almacenamiento | Ambient, chilled, frozen or hazmat. A product can only go to locations of a compatible zone. | Catalog, Warehouse |
| Product family | Familia logística | A group of products stored together, such as shirts or shoes. A product has one at most; a zone keeps any number. | Catalog, Warehouse |
| Turnover class | Clase de rotación (ABC) | How fast a product leaves: A for the fast movers, C for the slow ones. Set by hand until there are shipments to compute it from. | Catalog |
| Product | Artículo | Something the warehouse stores. | Catalog |
| SKU | Referencia | The tenant's own product code. Unique per tenant. | Catalog |
| GTIN | EAN, código de barras | GS1 barcode number with a check digit. A product can have several. | Catalog |
| Base unit | Unidad base | The smallest unit stock is counted in, usually "each". All quantities in the domain are integers of this unit. | Catalog |
| Packaging level | Nivel de embalaje | A larger pack of the base unit: inner pack, case, pallet. It knows how many base units it holds. | Catalog |
| Tracking policy | Política de trazabilidad | Whether the product is tracked by lot, by expiry date, and its minimum remaining shelf life at receipt and at shipment. | Catalog |
| Lot | Lote | A production batch. Stock of different lots is never mixed in one `StockItem`. | Inventory |
| Expiry date | Fecha de caducidad | Last day the stock can be sold. | Inventory |
| Minimum shelf life | Vida útil mínima | Days of validity that must remain when stock is received or shipped. | Catalog |

## Stock

| Term | Spanish | Meaning |
|---|---|---|
| Stock item | Existencia | The quantity of one product, in one location, of one lot and expiry date, with one stock status. |
| Stock status | Estado del stock | Available, quarantine, damaged, on hold. Only available stock can be allocated. |
| Movement | Movimiento | An immutable record that stock changed: receipt, transfer, issue, adjustment. The ledger is the list of all movements. |
| On hand | Existencias físicas | Units physically in a stock item. |
| Reserved | Reservado | Units promised to an order and not yet picked. |
| Available | Disponible | On hand minus reserved, of stock that is in available status. |
| Stock overview | Vista de stock | What the tenant holds, by product and location, as the read model shows it a moment after each movement. |
| Adjustment | Regularización | A movement that corrects stock after a count or a loss. It always has a reason and an author. |
| Reservation | Reserva de stock | The stock set aside for one order line, possibly spread over several stock items. |
| Allocation | Asignación | Choosing which stock items cover an order line. Uses FEFO or FIFO. |
| Shortage | Faltante | The ordered units that no stock could cover when an order was allocated. It is reported, not an error, and allocating again covers it when stock arrives. |
| FEFO | Primero en caducar, primero en salir | First expired, first out. Used for products tracked by expiry. |
| FIFO | Primero en entrar, primero en salir | First in, first out. Used for the rest. |

## Inbound

| Term | Spanish | Meaning |
|---|---|---|
| ASN | Aviso de envío, entrada prevista | Advance shipping notice: what a supplier says it will deliver. Receiving is always against an ASN. |
| Receipt | Recepción | One receiving session at a dock, recording what physically arrived. |
| Discrepancy | Discrepancia, incidencia | A difference between the ASN and what arrived: shortage, excess, unexpected product, damage. |
| Putaway | Ubicación de entrada, almacenaje | Moving received goods from the dock to a storage location. |
| Putaway strategy | Estrategia de ubicación | The rule that proposes the destination: first free (the first empty location along the picking route where the units fit), by product family (a zone that keeps the product's family), by turnover (fast movers near the start of the route). |
| Putaway task | Tarea de ubicación | Goods at the dock waiting to go to a location, with the suggested one. Confirmed when the operator scans the destination; split to send part of the units elsewhere. |
| Override reason | Motivo del cambio | Why the goods went somewhere other than the suggested location. |

## Outbound

| Term | Spanish | Meaning |
|---|---|---|
| Order | Pedido | A customer's request for products, imported from the ERP. |
| Order line | Línea de pedido | One product and quantity within an order. |
| Cut-off | Hora de corte | The time by which an order must be shipped to meet its delivery promise. |
| Backorder | Pendiente de servir | The part of an order that could not be served for lack of stock. The customer can accept it, and then the covered part of the order can go to the floor. |
| Wave | Ola | A group of orders released to the floor together. |
| Pick list | Hoja de picking | The list of lines one operator picks, ordered by route. |
| Pick line | Línea de picking | One location, product and quantity to take. |
| Short pick | Rotura de picking | The operator finds less stock than the system said. |
| Serpentine route | Ruta en serpentina | Walking aisles alternately up and down, so the operator never backtracks: the first aisle with something to pick is walked one way, the next the other way, and so on. |
| Packing | Embalaje | Putting picked goods into parcels. |
| Parcel | Bulto | One physical package, identified by an SSCC. |
| SSCC | Código de serie de unidad de envío | 18-digit GS1 code that identifies one parcel or pallet. Printed as a label. |
| Shipment | Expedición | The parcels of one order, handed to the carrier. |
| Dispatch | Salida, despacho | The moment a shipment leaves the warehouse. |
