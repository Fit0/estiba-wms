---
title: "An anti-corruption layer for a legacy ERP"
date: 2026-11-14
lang: en
translation: 2026-11-14-capa-anticorrupcion-erp-legacy.es.md
tags: [symfony, ddd, anti-corruption-layer, strangler-fig, legacy, integration, wms]
---

# An anti-corruption layer for a legacy ERP

Every warehouse system I've worked on ends up talking to an ERP. The ERP is usually older than the warehouse system, older than the team, and sometimes older than the building it runs in. It has its own words for things. It writes dates day first. It answers with numbers instead of errors. And the integration is where a clean domain gets dirty, one column name at a time.

In [Estiba](https://github.com/Fit0/estiba-wms) I wanted to try the opposite. The ERP can be as odd as it likes, as long as the oddness stops at one door. This post is about that door, about the fake ERP I had to build to test it, and about what went wrong on the way. At the end there's a note on the strangler fig pattern, because the door turns out to be the thing that makes it possible.

## An ERP that is old on purpose

A portfolio project doesn't come with a real ERP, so I wrote one, in `erp-legacy/`. I tried to make it old in the ways that cause trouble:

- PHP 5.6, global functions, `include`, SQLite.
- Spanish names for everything: `NUMERO_PEDIDO`, `ARTICULO`, `ConfirmarEnvio`.
- Orders leave as CSV files in Latin-1, separated by `;`, with dates as `dd/mm/yyyy` and urgency as one letter (`B`, `N`, `A` or `U`).
- Shipments come back over SOAP, and the ERP never raises a fault for a business problem. It answers with a code. 0 is recorded, 1 is already confirmed, 2 is no such order, and so on.

The SOAP endpoint is written by hand. I had planned to use `ext-soap`, but the `php:5.6` image sits on a Debian whose package mirrors are gone, and installing the extension meant rewriting apt sources. Hand-made SOAP was common back then anyway. What it costs: the endpoint is my own code, so it may accept things a real server would refuse. I'll come back to that.

## One context, two ports

Estiba is a modular monolith, so the integration is a bounded context, `Erp`. It may depend on the published contracts of Catalog, Outbound and Warehouse, and nothing depends on it. Inside there are two ports:

- `ErpOrderFiles` reads what the ERP leaves behind.
- `ErpGateway` tells the ERP that a shipment left.

Their implementations, `CsvErpOrderFiles` and `SoapErpGateway`, are the only code that knows about CSV, Latin-1 or SOAP. The ERP's model of an order stays in `Erp/Domain`, with the ERP's own words, and one class turns it into warehouse language:

| The ERP says | The warehouse says |
|---|---|
| `NUMERO_PEDIDO` | the reference of the order, and its identity |
| `ARTICULO` | a product of the catalogue, found by SKU |
| `URGENCIA` `B`, `N`, `A`, `U` | priority `low`, `normal`, `high`, `urgent` |
| `FECHA_LIMITE` `08/11/2026 14:00`, Spain's time | an instant, `2026-11-08T13:00:00+00:00` |

What comes out of the translator is an `IncomingOrder` that doesn't know the ERP exists. Outbound registers it with the same `RegisterOrder` command the API uses, so an order from the ERP follows the same rules as one typed by hand. The ERP's order number is the identity: registering a reference that already exists isn't an error, it answers `AlreadyRegistered`. That one decision is what makes importing the same file twice harmless.

## Errors that say what to do

The ERP's codes are a language, and I don't want the rest of the code to learn it. The gateway reads the code and throws something that says what to do about it:

```php
return match ($code) {
    self::CODE_OK => ConfirmationOutcome::Confirmed,
    self::CODE_ALREADY_CONFIRMED => ConfirmationOutcome::AlreadyConfirmed,
    self::CODE_NOT_EXPORTED => throw ErpUnavailable::because($message),
    default => throw ErpRejectedShipment::withCode($confirmation->orderReference, $code, $message),
};
```

`ErpUnavailable` means try again later. `ErpRejectedShipment` means don't, because the same message will get the same answer. Code 3, "not exported yet", is a matter of time, so it's the first kind. Code 1 is a success, and I'll explain why in a minute.

## A bug the ERP found for me

The ERP gives due times in Spain's clock, `08/11/2026 14:00`, and the translator turned them into proper instants. Then the order came back from the database an hour late.

The cut-off wasn't converted to UTC before it was saved, and the column keeps no time zone. So 14:00 in Madrid was stored as 14:00 and read back as 14:00 UTC. The API had the same problem for any offset other than zero. I only noticed because the ERP speaks Madrid time. The cut-off is now converted to UTC when the order is registered.

## Pulling files on a timer

Symfony Scheduler sends `ImportErpOrders` every 30 seconds, and a `scheduler` container consumes it. The SFTP part uses phpseclib, which is pure PHP, so there's no extension to install. Three decisions are worth writing down:

1. **Idempotent by order, not by file.** The importer registers the order and then moves the file. If the process dies between the two, the next run finds the order already registered and just moves the file.
2. **One bad file doesn't stop the others.** Anything the domain refuses sends that file to `rejected/` with a warning, and the run goes on. A failure of the SFTP server or the database stops the run, and the next tick starts over with the same files.
3. **The import runs on a bus without a transaction.** This one is easy to get wrong. If the whole import ran inside the command bus transaction, a failure while registering one order, even one I catch, marks the transaction as rollback-only and loses the orders already registered. So each order goes through the command bus with its own transaction, and the job decides file by file.

Files are moved, never deleted. `processed/` and `rejected/` are the trail for whoever has to find out what the ERP sent.

## Telling the ERP: an outbox

When an order leaves the warehouse, the ERP has to hear about it. But the ERP can be down, or slow, or busy with its own export, and a dispatch can't wait for it or fail because of it.

Outbound publishes `ShipmentLeft`, which goes through Redis to the worker. A policy in `Erp` writes a row in `erp_shipment_outbox`, but only if the order came from the ERP. Orders typed into Estiba would only get "no such order". Every 30 seconds a job sends what's due. After a failure it waits a minute, then doubles the wait up to an hour, and the eighth failure is the last, about three hours in all. If the ERP refuses a confirmation, the row goes to `failed` at once with the reason, because the same message would be refused again.

Delivery is at least once. If the process dies after the ERP took a confirmation and before the row is marked, it's sent again. That's why code 1 matters: the ERP answers "already confirmed", the gateway treats that as success, and nobody notices. I picked that behaviour for the simulated ERP deliberately. Real ERPs vary here, and on a real integration it's one of the first things I'd check. Without it you have to build the idempotency yourself.

## Keeping the contract honest

The gateway tests use a copy of the ERP's WSDL, and the ERP has the original. Two copies of a contract will drift. `WsdlContractTest` compares them, ignoring line endings and the service location, and fails if they differ. In a container that only mounts `backend/` it can't see the original, so it skips and says why. CI checks out the whole repository, so it runs there.

This is also my answer to the lenient endpoint. The tests treat the WSDL as the contract, not whatever my hand-written SOAP happens to accept.

Last piece: when it works nobody looks at it, and when it doesn't, the only places to look were an SFTP folder, a table and the logs. So every file the import handles gets a row in `erp_import_log`, and a supervisor can read imports and confirmations on one screen and put a failed confirmation back in the queue. The retry doesn't call the ERP. It puts the row back to `pending`, and the job stays the only sender.

## Where the strangler fig fits

The strangler fig pattern is about replacing a legacy system without a big bang. You put something new in front, route one piece at a time to it, and the old system shrinks until you can switch it off.

Estiba isn't strangling this ERP. The ERP owns the customer orders and I never meant to replace it. But the `Erp` context gives you the seam where you'd do it. If the ERP had to go, the work would sit on the other side of two ports: a new adapter for each, or a new use case in the warehouse and the end of one flow. Outbound and Inbound wouldn't notice.

I haven't built that here, and I don't want to pretend otherwise. Later in this project I do the real thing: a PHP 5.6 application with Symfony in front of it, routing the migrated routes and moving one module at a time.

## What it doesn't do

- One tenant and one warehouse receive the ERP's orders. The ERP doesn't say which, so they're settings.
- Only one `scheduler` may run. With two, the same file could be read twice at once.
- A rejected file isn't retried. You fix the cause and put the file back by hand.
- The import log has no retention.
- If Redis loses the event between the dispatch and the outbox, a confirmation is lost. A check comparing shipped orders with the outbox would catch it. It isn't built.
- The SFTP host key isn't verified in development.
- Only orders and shipment confirmations cross. Master data and purchasing don't.

## What's next

The AI features: classifying products, suggesting locations and explaining anomalies, all through Claude and all read-only.

The code is on [GitHub](https://github.com/Fit0/estiba-wms). The decisions are in ADRs [0046](../adr/0046-a-simulated-legacy-erp.md), [0047](../adr/0047-an-anti-corruption-layer-for-the-erp.md), [0048](../adr/0048-a-scheduled-import-of-erp-orders.md), [0049](../adr/0049-a-database-outbox-for-erp-confirmations.md), [0050](../adr/0050-an-integration-panel-and-a-contract-test-for-the-erp.md) and [0051](../adr/0051-the-erp-integration-as-a-whole.md), and the diagrams are in [ERP integration](../architecture/erp-integration.md).
