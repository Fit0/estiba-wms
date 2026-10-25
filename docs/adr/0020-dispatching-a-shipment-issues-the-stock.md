# 0020. Dispatching a shipment issues the stock

- Status: Accepted
- Date: 2026-10-25

## Context

The picked goods wait at staging, promised to their order ([ADR 0016](0016-confirming-picks-and-moving-the-stock-to-staging.md)), and are packed into parcels with their SSCC ([ADR 0017](0017-packing-parcels-with-sscc-labels.md)). Until the parcels are handed to the carrier the warehouse still holds the goods in its books, and the order is still open. Dispatch is the moment that changes. Several things had to be decided:

- **What dispatching does to the stock**, and to the reservation that holds it.
- **Whether that is a policy** that runs after the shipment is dispatched (the event board) or part of the dispatch.
- **What the order becomes**, and what it can no longer do.
- **Who may dispatch**, and what is asked of them.

## Options considered

1. **A policy on `ShipmentDispatched`** that consumes the reservations, in its own transaction, as the board shows. The shipment can be dispatched and the stock still on the books for as long as the policy has not run, or if it fails for good.
2. **The dispatch consumes the reservations in the same transaction** (the way each pick moves its stock, ADR 0016). There is no moment in which the goods have gone and the books say they have not.
3. **Issue the stock with a free `StockMovements::issue` call** and leave the reservation to expire. It leaves a reservation promising units that do not exist.
4. **A new contract call, `StockReservations::consume`**, that issues what the reservation holds, from where it waits, and ends the reservation.

## Decision

- **The dispatch is one transaction** (options 2 and 4). `DispatchShipment` dispatches the shipment, ships the order and calls `StockReservations::consume` for each reservation of the order, and publishes the events once it commits. A dispatch that cannot issue its stock does not happen.
- **`consume` ends a promise where the goods leave** (STK-16). For each stock item the reservation holds, it takes the promise back (`unreserve`) and issues the units (`issue` movement, with the reference `shipment:<id>` and the user who dispatched). The reservation becomes `consumed`, holds nothing and cannot be released, consumed or picked again (STK-13). A reservation that holds nothing, because every unit was short picked, is consumed too, so no line is left active.
- **The order is shipped** (OUT-25): every line ships what it picked, never more (OUT-03), so a short pick ships the units found and the rest stay as the shortage. A shipped order cannot be cancelled.
- **The shipment is dispatched**: it keeps the carrier, the tracking number if the carrier gave one, and the moment. A dispatched shipment cannot change (OUT-24).
- **A supervisor dispatches**, naming the carrier. It is the handover of goods the company will invoice, and it cannot be undone, so it is not floor work.
- **What the board calls a policy that tells the ERP** is not built: it arrives with the ERP integration (E08), as a policy on `ShipmentDispatched`.

## Consequences

- A dispatch of an order of many lines makes one issue movement for each stock item that holds units of it. It is fine for a floor, and the movements go to the read model through the worker as any other (ADR 0012, ADR 0013).
- The shelf of a short pick keeps units that the system says are there and nobody found (ADR 0016). A dispatch does not fix that: a count does.
- A dispatch cannot be undone. Returns and the units of a shipment that the carrier sends back are out of the scope of version 1.
- The stock leaves from the staging stock item that the picks filled. If goods were moved from staging by hand between the pick and the dispatch, the reservation would still point at the stock item and the dispatch would fail for lack of units. Nothing in the system moves them, since they are promised (STK-02).
