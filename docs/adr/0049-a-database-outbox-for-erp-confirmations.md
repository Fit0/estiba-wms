# 0049. A database outbox for the confirmations to the ERP

- Status: Accepted
- Date: 2026-11-09

## Context

When an order that came from the ERP leaves the warehouse, the ERP has to hear of it (`ConfirmarEnvio`, ADR 0046). The ERP is another system: it can be down, slow, or in the middle of its own export. A dispatch cannot wait for it, nor fail because of it, and a confirmation must not be lost if the ERP is down for a night or the process dies right after the dispatch.

Three questions follow. How the dispatch hands the fact over to `Erp`, without `Erp` reaching into Outbound's domain or Outbound knowing about the ERP. Where a confirmation waits until it is sent. And what happens when sending fails.

## Options considered

For the trigger: **calling the ERP in the dispatch**, which ties the dispatch to the ERP's health; **a policy on the event bus with Messenger's retries**, simple but with no table to look at and no way to say what became of a shipment; or **an outbox in the database**, which costs a table and a job.

For the event: **`Erp` listens to Outbound's domain event** `ShipmentDispatched`, which breaks ADR 0003, or **Outbound publishes a contract event** with the plain values others need.

For which orders to confirm: **all of them**, so the ERP answers "no such order" (code 2) to every order typed into the warehouse, or **only the ones that came from the ERP**.

## Decision

- **Outbound publishes `ShipmentLeft`**, in its `Application/Contract`, from the dispatch, with the tenant, the shipment, the order reference, the carrier, the tracking number, the number of parcels and the units by product. It is the contract other contexts may listen to. It is routed to the async transport, like the notifications (ADR 0045): a failure of the listener never undoes a dispatch. The audit trail records it as `outbound.shipment_left`, since the describer now names events of a contract as it names those of a domain.
- **`Erp` keeps a table of the orders it imported** (`erp_imported_orders`), written by the importer when an order is registered or already was. `QueueShipmentConfirmation` listens to `ShipmentLeft` and queues a confirmation only if the order is in that table. Orders of the API, which the ERP does not know, are never queued.
- **The outbox is a table, `erp_shipment_outbox`,** with one row for each shipment, keyed by the shipment, in the state `pending`, `sent` or `failed`, with the attempts, the time of the next attempt and the last error. The confirmation is stored as JSON, in the words of the ERP's catalogue (SKUs): what was queued is what is sent. Queueing twice is one row, so a second delivery of the event does nothing. The table is read and written with SQL (a mapping describes it for the schema, as for the SSCC serials).
- **`ErpOutboxSender` sends what is due**, from the job bus every 30 seconds (`SendErpConfirmations`, in the `erp` schedule of ADR 0048). The ERP's answer, as the gateway already puts it (ADR 0047), decides: confirmed or already confirmed means `sent`; `ErpUnavailable` means try again; `ErpRejectedShipment` means `failed` at once, with the reason, because the same message would be refused again.
- **`RetryPolicy`**: after the first failure a minute, then double each time up to an hour, and the eighth failure is the last, which is about three hours in all. After it the row is `failed`. A run stops at the first confirmation that finds the ERP unreachable, since the rest would wait for the same timeout; the next run goes on with them.
- **A failed confirmation is not lost, nor retried.** It stays in the table with its reason and a warning is logged. Showing it and sending it again by hand is part of the integration panel of E08-05.
- `app:erp:send-confirmations` runs the same job once.

## Consequences

- A dispatch takes the same time with the ERP up or down, and a confirmation that could not be sent is a row that anyone can read.
- Delivery is at least once: if the process dies after the ERP took a confirmation and before the row is marked, it is sent again. That is safe because the ERP answers "already confirmed" (code 1) to a repeat, and the gateway treats it as success.
- Two moments are not in one transaction: the dispatch commits, then the event goes through Redis, then the row is written. A loss of Redis messages between the first and the last would lose a confirmation. The failed-message transport keeps what the listener could not handle (ADR 0012), and a reconciliation that compares shipped orders with the outbox is the safeguard if this ever matters.
- Only one `scheduler` may run. Two would read the same rows and send some twice, which the ERP tolerates.
- Orders imported before this change are not in `erp_imported_orders`, so their shipments are not confirmed. The development database is rebuilt by the demo; a real one would need the table filled from the orders the ERP sent.
- Tenants are not looked at when sending: the ERP is one, and the gateway does not depend on the tenant. A tenant with its own ERP would need the gateway chosen by tenant.
