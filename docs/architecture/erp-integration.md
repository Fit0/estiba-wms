# ERP integration

How Estiba and the legacy ERP exchange orders and shipments. The decisions are in [ADR 0051](../adr/0051-the-erp-integration-as-a-whole.md) and, for each part, in ADRs [0046](../adr/0046-a-simulated-legacy-erp.md) to [0050](../adr/0050-an-integration-panel-and-a-contract-test-for-the-erp.md). The contract with the ERP is in [erp-legacy/README.md](../../erp-legacy/README.md) and the code in [backend/src/Erp](../../backend/src/Erp/README.md).

## The pieces

What runs, and who talks to whom. The ERP and its SFTP server are the simulated ones and only start with `make erp-up`.

```mermaid
flowchart LR
    subgraph legacy["Simulated legacy ERP (erp-legacy/)"]
        erp["<b>erp</b><br/>PHP 5.6, SQLite<br/>Exports orders every 15 s<br/>SOAP: ConfirmarEnvio, ConsultarPedido"]
        sftp["<b>erp-sftp</b><br/>outbox/*.csv<br/>processed/ and rejected/"]
        erp -->|"writes one CSV per order<br/>Latin-1"| sftp
    end

    subgraph estiba["Estiba"]
        scheduler["<b>scheduler</b><br/>every 30 s:<br/>ImportErpOrders<br/>SendErpConfirmations"]
        php["<b>php</b><br/>API, command and query buses<br/>Outbound, Catalog, Warehouse"]
        worker["<b>worker</b><br/>asynchronous events"]
        db[("<b>database</b><br/>erp_imported_orders<br/>erp_shipment_outbox<br/>erp_import_log")]
        redis[("<b>redis</b><br/>async stream")]
        panel["<b>frontend</b><br/>Integración ERP (/erp)"]
    end

    scheduler -->|"reads, then moves files<br/>SFTP (phpseclib)"| sftp
    scheduler -->|"registers orders through<br/>OrderIntake of Outbound"| php
    scheduler -->|"ConfirmarEnvio<br/>SOAP (ext-soap)"| erp
    php -->|"ShipmentLeft"| redis
    redis --> worker
    worker -->|"queues a confirmation"| db
    scheduler <-->|"reads and writes<br/>the three tables"| db
    panel -->|"GET /api/erp/confirmations<br/>GET /api/erp/imports<br/>POST .../retry"| php
    php --> db

    classDef ext fill:#6b6b6b,stroke:#454545,color:#ffffff
    class erp,sftp ext
```

Inside the `Erp` context, the ERP's words are known in two adapters and one translator, and nowhere else:

```mermaid
flowchart LR
    subgraph erpctx["Erp context"]
        direction TB
        csv["CsvErpOrderFiles<br/>(CSV, Latin-1)"]
        inbox["SftpErpOrderInbox<br/>(SFTP)"]
        soap["SoapErpGateway<br/>(SOAP, codes)"]
        tr["ErpOrderTranslator<br/>ErpOrder to IncomingOrder"]
        imp["ErpOrderImporter"]
        snd["ErpOutboxSender"]
        imp --> inbox
        imp --> csv
        imp --> tr
        snd --> soap
    end

    catalog["Catalog<br/>ProductCatalog.findBySku"]
    outbound["Outbound<br/>OrderIntake, ShipmentLeft"]
    wh["Warehouse<br/>WarehouseDirectory"]

    tr --> catalog
    imp --> outbound
    imp --> wh
    outbound -. "ShipmentLeft<br/>(QueueShipmentConfirmation)" .-> snd
```

The `Erp` context depends on the contracts of Catalog, Outbound and Warehouse (ADR 0003). Nothing depends on it.

## An order comes in

Every 30 seconds. The ERP leaves a file once and never again, and Estiba moves it when it is done with it.

```mermaid
sequenceDiagram
    participant ERP as ERP
    participant SFTP as SFTP outbox/
    participant S as scheduler
    participant I as ErpOrderImporter
    participant O as Outbound (OrderIntake)
    participant DB as database

    ERP->>SFTP: writes .P2026000004.csv.tmp, then renames it
    S->>I: ImportErpOrders
    I->>SFTP: lists the files that end in .csv
    loop each file
        I->>SFTP: reads it
        I->>I: translates ERP words into warehouse words
        I->>O: register(order)
        O-->>I: Registered or AlreadyRegistered
        I->>DB: remembers the order in erp_imported_orders
        I->>DB: writes a row in erp_import_log
        I->>SFTP: moves the file to processed/ (or rejected/)
    end
```

What happens to a file:

| Situation | Result |
|---|---|
| Its orders were registered, or already were | `processed/` |
| It is malformed, names an unknown article, or Outbound refuses the order | `rejected/` with a warning. The other files go on |
| SFTP, the database or anything else that is not the file's fault fails | The file stays. The run stops and the next one starts over |

## A shipment goes back

The dispatch does not wait for the ERP. Only orders that came from the ERP are queued.

```mermaid
sequenceDiagram
    participant U as Supervisor
    participant O as Outbound
    participant R as Redis
    participant W as worker
    participant DB as erp_shipment_outbox
    participant S as scheduler
    participant G as SoapErpGateway
    participant ERP as ERP

    U->>O: dispatch the shipment
    O->>O: commit (order shipped, stock issued)
    O->>R: ShipmentLeft
    R->>W: ShipmentLeft
    W->>DB: pending row, if the order came from the ERP
    loop every 30 s
        S->>DB: confirmations that are due
        S->>G: confirmShipment
        G->>ERP: ConfirmarEnvio (SOAP)
        ERP-->>G: Codigo and Mensaje
        G-->>S: Confirmed, AlreadyConfirmed, or an exception
        S->>DB: updates the row
    end
```

The ERP's codes, as the gateway sees them:

| `Codigo` | Meaning | Becomes |
|---|---|---|
| 0 | Shipment recorded | `Confirmed` |
| 1 | Already confirmed | `AlreadyConfirmed`, a success |
| 3 | Not exported yet | `ErpUnavailable`: try again |
| 2, 4, 5, 6, 7, any other, or a fault that blames the message | Refused | `ErpRejectedShipment`: do not try again |
| A fault of the server, or no connection | | `ErpUnavailable` |

## Life of a confirmation

The states of a row in `erp_shipment_outbox`.

```mermaid
stateDiagram-v2
    [*] --> pending: ShipmentLeft of an ERP order
    pending --> sent: ERP took it, or already had it
    pending --> pending: ERP unavailable, wait 1 min doubling up to 1 h
    pending --> failed: ERP refused it, or the eighth failure
    failed --> pending: a supervisor retries it from the panel
    sent --> [*]
```

A retry from the panel puts the row back to `pending`, with the attempts at zero and due now. It does not call the ERP itself: the job is the only sender, so a person and the schedule never send the same confirmation at once.

## Where to look when something is wrong

| Question | Where |
|---|---|
| What did the ERP send, and was it taken? | Screen "Integración ERP", imports. Table `erp_import_log`. The `processed/` and `rejected/` folders of the SFTP server |
| Was the shipment confirmed? | The same screen, confirmations. `select order_reference, status, attempts, last_error from erp_shipment_outbox;` |
| Is the schedule running? | `make erp-logs` |
| Run an import or a send now | `docker compose exec php bin/console app:erp:import-orders` and `app:erp:send-confirmations` |
| Is the ERP itself fine? | `make erp-check` |
