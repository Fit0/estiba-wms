# Aggregates and invariants

An aggregate is the unit that is loaded, changed and saved as a whole, and the unit that protects its own rules. Each rule has an identifier so tests and code comments can point to it, for example `// STK-01`.

Every aggregate, except the tenant itself, carries a `TenantId`. Identifiers are UUID v7. A context refers to another context's aggregate by identifier only (ADR 0003).

## Identity

### Tenant

Identity: `TenantId`, the same identifier every other aggregate carries. Holds the name and the default language, which is the language used where no request says otherwise (background jobs, printed documents).

| ID | Invariant |
|---|---|
| IDN-07 | The name has 1 to 100 characters and no control characters. |

### User

Identity: `UserId`. Holds the tenant, the email address, the password hash, the roles and the status (active or disabled). Roles are `admin`, `supervisor` and `operator`, and each includes the ones below it. A user is created with `User::register()`, which checks IDN-01 through the `EmailRegistry`.

| ID | Invariant |
|---|---|
| IDN-01 | An email address belongs to one user in the whole system, ignoring case. A unique database constraint backs the check against races. |
| IDN-02 | An email address is valid, at most 254 characters, and is kept in lower case. |
| IDN-03 | A password has 12 to 72 characters (72 is bcrypt's limit in bytes). It exists as plain text only inside `PlainPassword`, which cannot be printed, logged or serialized, and only a hash is ever stored. |
| IDN-04 | A user has at least one role. |
| IDN-05 | A user belongs to one tenant for its whole life: nothing can move it. |
| IDN-06 | A disabled user cannot sign in, and a token it already holds stops working at once, because the user is loaded on every request. Enforced by the security layer's user checker. |

## Catalog

### Product

Identity: `ProductId`. Holds the `Sku`, the GTINs, the base unit, the packaging levels, the tracking policy, the storage class and the status (active, blocked, discontinued).

| ID | Invariant |
|---|---|
| CAT-01 | The SKU is unique per tenant, ignoring case and surrounding spaces. |
| CAT-02 | Every GTIN passes the GS1 check digit and belongs to one product of the tenant. |
| CAT-03 | The base unit has quantity 1. Every packaging level holds a positive whole number of base units, and each level is larger than the one before. |
| CAT-04 | A product tracked by expiry date is also tracked by lot. |
| CAT-05 | The tracking policy is fixed when the product is registered. A product that needs another policy is registered again under a new SKU. |
| CAT-06 | A blocked or discontinued product cannot be received or allocated. Consumers read the status from the product snapshot. |
| CAT-07 | The minimum shelf life, at receipt and at shipment, applies only to products tracked by expiry date and is zero or more days. |
| CAT-08 | A discontinued product is final: it cannot be blocked, unblocked or changed. Products are never deleted, because stock history and orders refer to them. |
| CAT-09 | The length, width, height (millimetres) and weight (grams) of the base unit are positive whole numbers. |

GTIN-8, GTIN-12, GTIN-13 and GTIN-14 are all accepted. The same item printed as a UPC and as an EAN with a leading zero is one GTIN, so uniqueness (CAT-02) compares the numbers padded to 14 digits.

## Warehouse

### Warehouse

Identity: `WarehouseId`. Holds its zones (`ZoneId`, code, name, storage class, picking sequence). A warehouse has few zones, so they live inside it.

| ID | Invariant |
|---|---|
| WH-01 | The warehouse code is unique per tenant. |
| WH-02 | A zone code is unique within its warehouse. |

### Location

Identity: `LocationId`. A separate aggregate, because a warehouse has thousands of locations. Holds the warehouse and zone identifiers, the `LocationCode`, the type, the capacity (maximum weight, volume, pallet positions), the pick sequence and the status (active, blocked, with a reason).

| ID | Invariant |
|---|---|
| WH-03 | The location code matches `aisle-module-level` and is unique within its warehouse. |
| WH-04 | Every capacity value is positive. |
| WH-05 | The zone belongs to the location's warehouse. |
| WH-06 | A blocked location is never offered for putaway or allocation. Consumers read the status from the location snapshot. |
| WH-07 | Locations are never deleted, only blocked, because stock history refers to them (DD-4). |

A location is created only through `Warehouse::defineLocation()`, which checks WH-03 and WH-05.

WH-01 and WH-03 need to know about other aggregates (the other warehouses of a tenant, the other locations of a warehouse). The factory receives a small registry interface (`WarehouseCodeRegistry`, `LocationCodeRegistry`), so the rule is enforced in the domain and a unit test can fake it. A unique database constraint backs it against two requests racing each other.

## Inventory

### StockItem

Identity: `StockItemId`, with a unique constraint on tenant, product, location, lot, expiry date and status (DD-1). Holds `onHand`, `reserved`, the moment units first arrived (`firstReceivedAt`, which orders stock for FIFO) and a version for optimistic locking. Reserving units moves none of them, so it records no movement; every change of `onHand` appends a `StockMovement` row (type, quantity change, reference, reason, author, time). Two requests that change the same stock item cannot both succeed: the second one is refused when it saves (answered as `shared.concurrent_update`, 409), which is what keeps two orders from promising the same units.

| ID | Invariant |
|---|---|
| STK-01 | `onHand` is never negative. |
| STK-02 | `reserved` never exceeds `onHand`. Units that are promised cannot be issued, adjusted away or transferred, and a version stops two concurrent reservations from both winning. A database `CHECK` backs it. |
| STK-03 | Every change of `onHand` is recorded by exactly one movement, in the same transaction. |
| STK-04 | A movement is never updated or deleted. A mistake is fixed with a compensating movement. |
| STK-05 | The sum of a stock item's movements equals its `onHand`. A reconciliation test checks it. |
| STK-06 | Only stock in available status, in an active reserve or pick face location, can be reserved. Stock at a dock, in staging or in quarantine is not offered. |
| STK-07 | Lot and expiry date are present exactly when the product's tracking policy asks for them. |
| STK-08 | A transfer takes from one stock item and adds to another, both or neither. |
| STK-09 | An adjustment needs a reason and an author. |
| STK-10 | Reserved units cannot change stock status. |

### StockReservation

Identity: `ReservationId`. Holds the reference of what it serves (an opaque text, for an order line `order:<orderId>:<lineNumber>`), the product, the requested quantity, the strategy (FEFO or FIFO), the allocations (stock item and quantity) and the status. A reference has one reservation: asking again tops it up and never reserves twice. The quantity asked at first cannot change.

```mermaid
stateDiagram-v2
    [*] --> Active
    Active --> Consumed: stock issued at dispatch (with picking)
    Active --> Released: order cancelled or line shorted
    Consumed --> [*]
    Released --> [*]
```

| ID | Invariant |
|---|---|
| STK-11 | The allocated quantity is the sum of the allocations and never exceeds the requested quantity. Allocating less is allowed, and the shortage is reported. |
| STK-12 | Stock is chosen by earliest expiry date first for expiry-tracked products (FEFO), then by earliest receipt date (FIFO). Stock that would expire before the product's minimum shelf life at shipment, counted from the day of the allocation, is skipped. Stock that never expires goes after stock that does. Equal stock is chosen by identifier, so the plan is repeatable. |
| STK-13 | A reservation is consumed once. Units that were not picked go back to available. |
| STK-16 | Consuming a reservation issues the units it holds from the stock items that hold them, as `issue` movements, and ends the promise with them (the units cannot be issued while promised, STK-02). A reservation that holds nothing, because every unit was short picked, is consumed all the same. A consumed reservation has nothing to release, and cannot be consumed, allocated or picked again. |
| STK-14 | Releasing a reservation is safe to repeat: the second release gives nothing back. A released reservation takes no more units. |
| STK-15 | A pick moves units of a reservation from the stock item that holds them to a stock item in another location, as a transfer of the ledger, and the units stay promised there. The units not found stop being promised, and the reservation covers that much less. A pick never takes more than the reservation holds in that stock item. |

## Inbound

### Asn

Identity: `AsnId`. Holds the supplier, the expected date, the external reference, the lines (product, expected quantity, received quantity, optional lot and expiry) and the receipt in progress, if any. Receipts are opened and closed through the ASN, so it adds what each receipt counted to its lines and moves through its statuses by itself.

```mermaid
stateDiagram-v2
    [*] --> Expected
    Expected --> PartiallyReceived: first receipt closes
    PartiallyReceived --> Received: all lines covered
    Expected --> Received: all lines covered
    Received --> Closed
    Expected --> Cancelled
    PartiallyReceived --> Closed: closed with shortage
    Closed --> [*]
    Cancelled --> [*]
```

| ID | Invariant |
|---|---|
| INB-01 | Every line's product exists and is active. |
| INB-02 | Expected quantities are positive whole numbers of the base unit. |
| INB-03 | The external reference is unique per tenant, so importing the same ASN twice changes nothing. |
| INB-04 | A closed or cancelled ASN cannot be received against. |
| INB-15 | An ASN has at least one line, and a product appears on one line only. |
| INB-16 | An ASN has at most one open receipt. It cannot be cancelled or closed while a receipt is open. |

### Receipt

Identity: `ReceiptId`. Holds the ASN, the dock location, what it expects of each product (the units of each ASN line still to come when it opened), the received lines (product, quantity, lot, expiry, condition) and the status (open, closed). Discrepancies are derived by comparing received and expected quantities: shortage, excess, unexpected product and damaged units. They are never stored, so they cannot disagree with the counts.

| ID | Invariant |
|---|---|
| INB-05 | A receipt belongs to exactly one ASN. |
| INB-06 | A scanned GTIN that is not on the ASN is recorded as an unexpected-product discrepancy. It is not refused. |
| INB-07 | Lot and expiry date are required when the product tracks them, and the expiry date is not in the past. |
| INB-08 | Receiving more than the ASN quantity is a discrepancy. Closing the receipt then needs supervisor approval. The default tolerance is zero. An unexpected product counts as more than the ASN quantity, and receiving more of a product that is already over withdraws the approval. |
| INB-09 | A closed receipt cannot change, and closing it publishes `ReceiptClosed` once. |
| INB-10 | Damaged goods enter stock in quarantine status, never available. |

### PutawayTask

Identity: `PutawayTaskId`. Holds the receipt, the ASN reference, the goods (product, quantity, lot, expiry and whether they are in quarantine), the source dock, the suggested location, the actual location, an override reason, who confirmed it and the status (pending, confirmed). Tasks are planned when a receipt closes, one for each product, lot, expiry date and condition it brought, at the location the configured putaway strategy suggests (ADR 0011). Damaged goods go to quarantine locations, the rest to reserve or pick faces.

| ID | Invariant |
|---|---|
| INB-11 | The destination is an active location of a type that stores the goods (quarantine for damaged goods, reserve or pick face for the rest), whose zone suits the product's storage class and which has room for the quantity by weight and volume. It is not the dock the goods are on. |
| INB-12 | Confirming moves exactly the task quantity. A smaller move is made by splitting the task first. |
| INB-13 | Choosing a location other than the suggested one needs a reason. A task without a suggestion needs none. |
| INB-14 | The putaway tasks of a receipt's goods add up to exactly the quantity received: splitting a task moves units to a new task and never loses any. |

## Outbound

### Order

Identity: `OrderId`. Holds the external reference, the warehouse it is served from, the wave it belongs to (if any), whether the customer accepted the backorder, the customer, the ship-to address, the priority (low, normal, high, urgent), the cut-off time and the lines (a line number from 1, the product, and the ordered, allocated, picked and shipped quantities, plus the reservation that holds the stock). Allocating an order asks Inventory for one reservation per line that is not fully covered, and doing it again tops them up.

```mermaid
stateDiagram-v2
    [*] --> Registered
    Registered --> Allocated: all lines reserved
    Registered --> PartiallyAllocated: some lines short
    PartiallyAllocated --> Allocated: stock arrives
    Allocated --> InWave
    PartiallyAllocated --> InWave
    InWave --> Picked
    Picked --> Shipped: dispatched
    Registered --> Cancelled
    Allocated --> Cancelled
    PartiallyAllocated --> Cancelled
    InWave --> Cancelled
    Shipped --> [*]
    Cancelled --> [*]
```

| ID | Invariant |
|---|---|
| OUT-01 | The external reference is unique per tenant, so importing the same order twice changes nothing. |
| OUT-02 | Ordered quantities are positive whole numbers of the base unit, and the product is active. |
| OUT-03 | For every line, allocated never exceeds ordered, picked never exceeds allocated, and shipped never exceeds picked. Units an operator does not find are no longer allocated, so the shortage of the line, and the backorder, grow by them. The order is picked when everything still allocated has been picked. |
| OUT-04 | Cancelling an order releases its reservations, in the same transaction. A cancelled order cannot be cancelled or allocated again. A picked order cannot be cancelled: its goods are off the shelves. A shipped order cannot be cancelled either: its goods are gone. |
| OUT-05 | An order belongs to at most one open wave. |

### Wave

Identity: `WaveId`. Holds the warehouse, the orders (by identifier), the pick lists it was released into and the status (planned, released, completed, cancelled). Creating a wave asks each order to join, and the order decides (OUT-06). Releasing it builds its pick lists in the same transaction.

| ID | Invariant |
|---|---|
| OUT-06 | Only orders that are allocated, or partially allocated with the backorder accepted, can join a wave. The customer's acceptance is recorded with `POST /orders/{id}/accept-backorder`, and only a partially allocated order has a backorder to accept. |
| OUT-07 | Once released, the orders of a wave cannot change: none leaves, and an order of the wave cannot be cancelled or allocated again. While the wave is planned, a cancelled order simply leaves it. |
| OUT-08 | A wave completes when all its pick lists are completed or cancelled, and one at least is completed. A completed wave cannot be cancelled or released again. |
| OUT-14 | All the orders of a wave are served from the same warehouse, because a pick list walks one. A wave holds 1 to 200 orders, none twice. |
| OUT-15 | The pick lists of a wave ask for exactly the units reserved for its orders, no more and no fewer. |
| OUT-16 | A wave is released into 1 to 50 pick lists, and never into more than it has picks. Cancelling a wave cancels its lists and frees its orders, which stay allocated: their reservations are not released. |

### PickList

Identity: `PickListId`. Holds the wave, the assigned operator, the status (generated, in progress, completed or cancelled) and the lines: each has its position on the walk, the order line, the product, the stock item and location to take the units from, the lot, the expiry date, the quantity to pick, the quantity picked, whether it is confirmed and, for a short pick, its reason.

```mermaid
stateDiagram-v2
    [*] --> Generated
    Generated --> InProgress: operator starts
    InProgress --> Completed: every line picked or shorted
    Generated --> Cancelled
    InProgress --> Cancelled
    Completed --> [*]
    Cancelled --> [*]
```

| ID | Invariant |
|---|---|
| OUT-09 | Lines follow the serpentine route: zone by zone in the order of their pick sequence, aisle by aisle inside each zone (alphabetically), the first aisle visited in ascending pick sequence, the second descending, and so on. Aisles with nothing to pick are not visited, so they do not turn the next one around. Several pick lists are consecutive stretches of the same route. |
| OUT-10 | The picked quantity never exceeds the quantity to pick, and is never negative. Picking less, even nothing, is a short pick and needs a reason. A line is confirmed once. |
| OUT-11 | Each pick is confirmed by scanning the location, then the product, and the lot when the line has one. All must match the line. The location is compared by its label without regard to case; the product by what its barcode belongs to (a barcode of nothing the tenant knows is a mismatch). |
| OUT-12 | A pick list has one assigned operator at a time. A supervisor can assign it until it is started; an operator who starts one nobody was assigned takes it. Only that operator starts it or confirms its picks. |
| OUT-13 | The list is complete only when every line is picked or shorted. |
| OUT-17 | Confirming a pick moves its units to a staging location in the same transaction and keeps them promised to the order. Nothing is picked from stock the order does not hold. |
| OUT-18 | A pick list with a pick confirmed cannot be cancelled, and so neither can its wave. |

### Shipment

Identity: `ShipmentId`. Holds the order, the warehouse, what the order had picked when packing began (by product), the status (packing, packed or dispatched) and the parcels. A parcel is a value inside it: the `SSCC`, the contents by product, the weight in grams and when it was closed. A parcel exists only closed: the packer fills a physical box and tells the system what is inside when it is sealed. Once dispatched it also holds the carrier, the tracking number if the carrier gave one, and when it went.

```mermaid
stateDiagram-v2
    [*] --> Packing
    Packing --> Packed: all picked units packed
    Packed --> Dispatched: dispatch
    Dispatched --> [*]
```

| ID | Invariant |
|---|---|
| OUT-19 | The SSCC has 18 digits and passes the GS1 check digit. It is the extension digit, the GS1 company prefix, a serial the company never repeats, and the check digit. It is assigned once, when the parcel is closed, and a parcel that is refused does not use a serial up. |
| OUT-20 | An order is packed in one shipment, and only a picked order with something picked can start one. Units that were short picked are not packed. |
| OUT-21 | A parcel holds at least one product, a positive number of units of each, and weighs between 1 and 1,000,000 grams. The same product twice in a parcel is added up. |
| OUT-22 | For every product, the units in parcels never exceed the units picked, and a parcel holds only products that were picked. |
| OUT-23 | A shipment is packed only when all picked units are in parcels. |
| OUT-24 | A packed shipment cannot change, and neither can a dispatched one. |
| OUT-25 | Only a packed shipment is dispatched, and only once, by a supervisor. Dispatching hands the parcels to a named carrier and, in the same transaction, ships the order (every line ships what it picked, OUT-03) and consumes its reservations, so the goods leave the stock (STK-16). A shipped order cannot be dispatched or cancelled again. |

## Audit

### AuditEntry

Identity: `AuditEntryId`. It is a record, not an aggregate: it has no behavior, nothing depends on it and it raises no events. One line of the audit trail: the tenant, the type of the event (`outbound.pick_list_started`), when it happened and when it was written, the user who caused it, the aggregate it is about and what the event carried.

| ID | Invariant |
|---|---|
| AUD-01 | An entry is written once and never changed or deleted. The trail only grows, and nothing in the API can alter it. |
| AUD-02 | Every domain event that is published is written down, in the transaction of the change that caused it: a change that is rolled back leaves no entry, and one that is committed always does. The actor is the signed-in user, or nobody when a policy runs in a worker. |
| AUD-03 | The trail holds no personal data. A field of an event marked `#[Sensitive]`, such as the email of a user, is left out. |
| AUD-04 | An entry belongs to a tenant and is read only by that tenant's supervisors and admins. An event that names no tenant takes the one of the signed-in user, and one with neither is reported in the log and not written. |

## Rules that span contexts

These cannot be guaranteed inside one aggregate. Each is handled by a policy or a contract call, and the table says how strong the guarantee is.

| Rule | How it holds | Guarantee |
|---|---|---|
| Stock is booked when a receipt closes | `ReceiptClosed` triggers `ReceiveStock` | Eventually, with retries |
| An order is allocated when it is registered | `OrderRegistered` triggers a call to the Inventory contract | Eventually, with retries |
| Reservations are released when an order is cancelled | `OrderCancelled` triggers the release | Eventually, with retries |
| Putaway goes only to an active, suitable location | `ConfirmPutaway` reads the location snapshot in the same request | Checked at the moment of the call |
| Stock never goes negative | `StockItem` enforces STK-01 on every change | Always |
