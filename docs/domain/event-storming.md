# Event storming

What happens in the warehouse, in order. Each flow reads from top to bottom and is cut into short diagrams so it stays readable.

| Shape | Meaning |
|---|---|
| Orange rounded box | **Event**: something that happened, in the past tense |
| Blue box | **Command**: a request to do something. The second line is the aggregate that handles it. |
| Purple slanted box | **Policy**: "whenever this event happens, do that" |
| Dark stadium | A person or external system that starts the flow |

A dashed arrow is an alternative outcome. Events published to other contexts are listed in the [context map](context-map.md).

## 1. Receiving and putaway

### 1.1 Receiving

```mermaid
flowchart TB
    erp(["Legacy ERP"]):::actor
    operator(["Operator"]):::actor

    c1["RegisterAsn<br/><i>Asn</i>"]:::cmd
    e1("AsnRegistered"):::evt
    c2["OpenReceipt<br/><i>Receipt</i>"]:::cmd
    e2("ReceiptOpened"):::evt
    c3["ReceiveItem<br/><i>Receipt</i>"]:::cmd
    e3("ItemReceived"):::evt
    e4("DiscrepancyDetected"):::evt
    c4["CloseReceipt<br/><i>Receipt</i>"]:::cmd
    e5("ReceiptClosed"):::evt

    erp --> c1 --> e1
    e1 --> c2
    operator --> c2 --> e2 --> c3
    c3 --> e3
    c3 -.-> e4
    e3 --> c4
    e4 --> c4
    c4 --> e5

    classDef cmd fill:#2b6cb0,stroke:#1a4971,color:#ffffff
    classDef evt fill:#f6ad55,stroke:#c05621,color:#1a1a1a
    classDef actor fill:#2d3748,stroke:#1a202c,color:#ffffff
```

| Command | Started by | Handled by | Rejected when |
|---|---|---|---|
| `RegisterAsn` | ERP import | `Asn` | The ASN reference already exists, a product is unknown or blocked, or a quantity is not positive |
| `OpenReceipt` | Operator | `Asn` | The ASN is closed or cancelled, or already has an open receipt. The location is not an active dock. |
| `ReceiveItem` | Operator scans a GTIN | `Receipt` | No product carries the GTIN, the product is blocked, a lot or expiry is missing for a tracked product (or given for an untracked one), or the expiry date has passed. A product that is not on the ASN is recorded as a discrepancy, not refused. |
| `ApproveOverReceipt` | Supervisor | `Receipt` | The receipt holds no more than the ASN announced, or the approval was already given |
| `CloseReceipt` | Operator | `Asn` | There are unapproved over-receipts. A supervisor must approve them first. |

Discrepancies found while receiving: shortage, excess, unexpected product, damaged goods. Damaged goods enter stock as *quarantine*, not *available*.

### 1.2 Booking the stock and putaway

```mermaid
flowchart TB
    operator(["Operator"]):::actor

    e5("ReceiptClosed"):::evt
    p1[/"Whenever a receipt closes:<br/>book the stock and<br/>plan its putaway"/]:::pol
    c5["ReceiveStock<br/><i>StockItem</i>"]:::cmd
    e6("StockReceived"):::evt
    c6["CreatePutawayTask<br/><i>PutawayTask</i>"]:::cmd
    e7("PutawayTaskCreated"):::evt
    c7["ConfirmPutaway<br/><i>PutawayTask</i>"]:::cmd
    e8("PutawayConfirmed"):::evt
    p2[/"Whenever a putaway is<br/>confirmed: move the stock"/]:::pol
    c8["TransferStock<br/><i>StockItem</i>"]:::cmd
    e9("StockTransferred"):::evt

    e5 --> p1
    p1 --> c5 --> e6
    p1 --> c6 --> e7
    e7 --> c7
    operator --> c7 --> e8 --> p2 --> c8 --> e9

    classDef cmd fill:#2b6cb0,stroke:#1a4971,color:#ffffff
    classDef evt fill:#f6ad55,stroke:#c05621,color:#1a1a1a
    classDef pol fill:#805ad5,stroke:#553c9a,color:#ffffff
    classDef actor fill:#2d3748,stroke:#1a202c,color:#ffffff
```

| Command | Started by | Handled by | Rejected when |
|---|---|---|---|
| `ReceiveStock` | Policy on `ReceiptClosed` | `StockItem` | Never refused: the receipt already checked the goods. Damaged units go to quarantine. |
| `CreatePutawayTask` | Policy on `ReceiptClosed` | `PutawayTask` | Never refused. Without a free location the task has no suggestion and the operator chooses. |
| `SplitPutawayTask` | Operator | `PutawayTask` | The task is confirmed, or one side would be left with no units |
| `ConfirmPutaway` | Operator scans the location | `PutawayTask` | The location is unknown, blocked, of a type that does not store the goods, incompatible with the product's storage class, has no room, or is the dock itself. Another location than the suggested one without a reason. |
| `TransferStock` | Policy on `PutawayConfirmed` | `StockItem` | The source would go below zero |

## 2. Order to picking

### 2.1 Allocation

```mermaid
flowchart TB
    erp(["Legacy ERP"]):::actor

    c1["RegisterOrder<br/><i>Order</i>"]:::cmd
    e1("OrderRegistered"):::evt
    p1[/"Whenever an order is<br/>registered: allocate stock"/]:::pol
    c2["AllocateStock<br/><i>StockReservation</i>"]:::cmd
    e2("StockReserved"):::evt
    e3("StockShortageDetected"):::evt
    c3["MarkOrderAllocated<br/><i>Order</i>"]:::cmd
    e4("OrderAllocated"):::evt

    erp --> c1 --> e1 --> p1 --> c2
    c2 --> e2
    c2 -.-> e3
    e2 --> c3
    e3 -.-> c3
    c3 --> e4

    classDef cmd fill:#2b6cb0,stroke:#1a4971,color:#ffffff
    classDef evt fill:#f6ad55,stroke:#c05621,color:#1a1a1a
    classDef pol fill:#805ad5,stroke:#553c9a,color:#ffffff
    classDef actor fill:#2d3748,stroke:#1a202c,color:#ffffff
```

| Command | Started by | Handled by | Rejected when |
|---|---|---|---|
| `RegisterOrder` | ERP import | `Order` | The external reference already exists, a quantity is not positive, or a product is blocked |
| `AllocateStock` | Policy | `StockReservation` | Never rejected for lack of stock. It reserves what exists and reports the shortage. |
| `MarkOrderAllocated` | Policy | `Order` | The order is cancelled |

With a shortage the order becomes *partially allocated* and the missing quantity stays as a **backorder**.

### 2.2 Waves

```mermaid
flowchart TB
    supervisor(["Supervisor"]):::actor

    c4["CreateWave<br/><i>Wave</i>"]:::cmd
    e5("WaveCreated"):::evt
    c5["ReleaseWave<br/><i>Wave</i>"]:::cmd
    e6("WaveReleased"):::evt
    p2[/"Whenever a wave is released:<br/>build one pick list<br/>per operator"/]:::pol
    c6["GeneratePickLists<br/><i>PickList</i>"]:::cmd
    e7("PickListGenerated"):::evt

    supervisor --> c4 --> e5
    supervisor --> c5
    e5 --> c5 --> e6 --> p2 --> c6 --> e7

    classDef cmd fill:#2b6cb0,stroke:#1a4971,color:#ffffff
    classDef evt fill:#f6ad55,stroke:#c05621,color:#1a1a1a
    classDef pol fill:#805ad5,stroke:#553c9a,color:#ffffff
    classDef actor fill:#2d3748,stroke:#1a202c,color:#ffffff
```

| Command | Started by | Handled by | Rejected when |
|---|---|---|---|
| `CreateWave` | Supervisor | `Wave` | An order is not allocated, or already belongs to an open wave |
| `ReleaseWave` | Supervisor | `Wave` | The wave has no orders |
| `GeneratePickLists` | Policy | `PickList` | Never rejected |

In the code the pick lists are made by `ReleaseWave` itself, in the same transaction, and not by a policy after the commit ([ADR 0015](../adr/0015-waves-and-pick-lists-in-serpentine-order.md)): a wave is never on the floor without its lists, and the supervisor gets them in the answer.

### 2.3 Picking

```mermaid
flowchart TB
    operator(["Operator"]):::actor

    e7("PickListGenerated"):::evt
    c7["ConfirmPickLine<br/><i>PickList</i>"]:::cmd
    e8("PickLineConfirmed"):::evt
    e9("ShortPickReported"):::evt
    p3[/"Whenever a short pick happens:<br/>reallocate, or leave a backorder"/]:::pol
    e10("PickListCompleted"):::evt
    p4[/"Whenever a pick list completes:<br/>move stock to staging"/]:::pol
    c8["TransferStock<br/><i>StockItem</i>"]:::cmd
    e11("StockTransferred"):::evt

    e7 --> c7
    operator --> c7
    c7 --> e8 --> e10
    c7 -.-> e9
    e9 --> p3
    p3 -.-> e10
    e10 --> p4 --> c8 --> e11

    classDef cmd fill:#2b6cb0,stroke:#1a4971,color:#ffffff
    classDef evt fill:#f6ad55,stroke:#c05621,color:#1a1a1a
    classDef pol fill:#805ad5,stroke:#553c9a,color:#ffffff
    classDef actor fill:#2d3748,stroke:#1a202c,color:#ffffff
```

| Command | Started by | Handled by | Rejected when |
|---|---|---|---|
| `ConfirmPickLine` | Operator scans location, product and lot | `PickList` | The scan does not match the line, or more is picked than asked |
| `TransferStock` | Policy on `PutawayConfirmed` | `StockItem` | The source would go below zero |

In the code, `ConfirmPickLine` moves the picked units to staging at once, one stop at a time, and not in a policy when the list completes ([ADR 0016](../adr/0016-confirming-picks-and-moving-the-stock-to-staging.md)). A short pick gives up the units that were not found: they stop being promised and become part of the shortage of the order line, and the policy below is left to a supervisor, who can allocate the order again and put it in another wave.

On a short pick the policy first tries to allocate the missing quantity from other stock. If there is none, the order line keeps the missing quantity as a backorder and the rest of the order continues.

## 3. Packing and dispatch

### 3.1 Packing

```mermaid
flowchart TB
    packer(["Packer"]):::actor

    e0("PickListCompleted"):::evt
    c1["StartPacking<br/><i>Shipment</i>"]:::cmd
    e1("PackingStarted"):::evt
    c2["CloseParcel<br/><i>Shipment</i>"]:::cmd
    e2("ParcelClosed"):::evt
    c3["CompletePacking<br/><i>Shipment</i>"]:::cmd
    e3("PackingCompleted"):::evt

    e0 --> c1 --> e1 --> c2
    packer --> c2 --> e2 --> c3
    packer --> c3
    c3 --> e3

    classDef cmd fill:#2b6cb0,stroke:#1a4971,color:#ffffff
    classDef evt fill:#f6ad55,stroke:#c05621,color:#1a1a1a
    classDef actor fill:#2d3748,stroke:#1a202c,color:#ffffff
```

| Command | Started by | Handled by | Rejected when |
|---|---|---|---|
| `StartPacking` | Policy | `Shipment` | The order has nothing picked |
| `CloseParcel` | Packer | `Shipment` | The parcel is empty, or it holds more than was picked |
| `CompletePacking` | Packer | `Shipment` | Picked quantities are not all packed |

Closing a parcel assigns its SSCC and produces its label.

### 3.2 Dispatch

```mermaid
flowchart TB
    supervisor(["Supervisor"]):::actor

    e3("PackingCompleted"):::evt
    c4["DispatchShipment<br/><i>Shipment</i>"]:::cmd
    e4("ShipmentDispatched"):::evt
    p1[/"Whenever a shipment is dispatched:<br/>consume the stock reservation"/]:::pol
    c5["IssueStock<br/><i>StockReservation</i>"]:::cmd
    e5("StockIssued"):::evt
    p2[/"Whenever a shipment is dispatched:<br/>tell the ERP"/]:::pol
    c6["ConfirmShipmentToErp<br/><i>Outbox</i>"]:::cmd
    e6("ShipmentConfirmedToErp"):::evt

    e3 --> c4
    supervisor --> c4
    c4 --> e4
    e4 --> p1 --> c5 --> e5
    e4 --> p2 --> c6 --> e6

    classDef cmd fill:#2b6cb0,stroke:#1a4971,color:#ffffff
    classDef evt fill:#f6ad55,stroke:#c05621,color:#1a1a1a
    classDef pol fill:#805ad5,stroke:#553c9a,color:#ffffff
    classDef actor fill:#2d3748,stroke:#1a202c,color:#ffffff
```

| Command | Started by | Handled by | Rejected when |
|---|---|---|---|
| `DispatchShipment` | Supervisor or carrier handover | `Shipment` | Packing is not complete |
| `IssueStock` | Policy | `StockReservation` | The reservation was already consumed |
| `ConfirmShipmentToErp` | Policy | Outbox | Never rejected. It retries until the ERP accepts it. |

## 4. Corrections and layout changes

Smaller flows that need no diagram.

| Command | Started by | Handled by | Event | Notes |
|---|---|---|---|---|
| `AdjustStock` | Supervisor | `StockItem` | `StockAdjusted` | Needs a reason. Cannot make stock negative. Creates a compensating movement, never edits an old one. |
| `ChangeStockStatus` | Supervisor | `StockItem` | `StockStatusChanged` | Moves units between available, quarantine, damaged and on hold. Reserved units cannot change status. |
| `BlockLocation` | Supervisor | `Location` | `LocationBlocked` | The location stops receiving putaways and allocations. Stock already in it stays. |
| `UnblockLocation` | Supervisor | `Location` | `LocationUnblocked` | |
| `BlockProduct` | Supervisor | `Product` | `ProductBlocked` | The product cannot be received or allocated. Existing reservations stand. |
| `UnblockProduct` | Supervisor | `Product` | `ProductUnblocked` | Rejected if the product is not blocked or is discontinued. |
| `DiscontinueProduct` | Supervisor | `Product` | `ProductDiscontinued` | Final (CAT-08). Works from active or blocked. |
| `CancelOrder` | ERP or supervisor | `Order` | `OrderCancelled` | Policy: whenever an order is cancelled, release its reservations. Not allowed after dispatch. |
