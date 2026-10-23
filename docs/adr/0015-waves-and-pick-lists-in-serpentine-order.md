# 0015. Waves and pick lists in serpentine order

- Status: Accepted
- Date: 2026-10-23

## Context

Once orders are allocated, the floor needs to know what to pick and in which order to walk. Picking each order alone sends operators back to the same aisles again and again. Grouping orders into a wave lets one walk serve many of them, and a good route keeps that walk short. Several things were open:

- **Who makes the pick lists, and when.** The event board shows a policy that reacts to `WaveReleased`.
- **How the route is worked out**, from codes such as `A-03-02` whose aisle is a letter, in warehouses with several zones.
- **How a wave is shared** among the operators who will walk it.
- **What happens to orders that are only partly covered**, and to orders that are cancelled when they are already in a wave.

## Options considered

1. **A policy makes the pick lists after the release commits** (as on the event board). The release stays small, but a wave can be on the floor without lists for a moment, or for good if the policy fails, and the supervisor does not get them in the answer.
2. **The release makes them in its own transaction.** The wave and its lists appear together or not at all.
3. **Route by location code only** (aisle, module, level). Simple, but the pick sequence that Warehouse keeps for the picking route would be ignored.
4. **Route by the zone's and the location's pick sequence, with the aisle deciding the direction** (serpentine).
5. **Split the wave by orders** (each operator takes whole orders) or **by the route** (each takes a stretch of the walk). Whole orders keep an order in one place and send every operator through every aisle. Stretches keep each operator in a part of the warehouse.

## Decision

- **Pick lists are made by the release itself** (option 2). The `GeneratePickLists` policy of the event board is folded into `ReleaseWave`, which asks Inventory where the reserved units are, orders them and shares them, all in the command's transaction. If anything fails, nothing is left half done.
- **The units to pick are the units reserved.** `StockReservations::stockOf()` says which stock items hold a reservation, and `LocationDirectory` says where each location is. A pick list therefore asks for exactly what was promised to the orders (OUT-15), with the lot and expiry date of the stock to take.
- **Serpentine** (option 4, OUT-09). `SerpentineRoute` is a pure domain service. It walks zone by zone in the order of their pick sequence, then aisle by aisle inside the zone, in alphabetical order with one letter before two. The first aisle that has something to pick is walked in the order of the pick sequence of its locations, the next in the opposite order, and so on, continuing across zones. Aisles with nothing to pick are not counted. Equal places are told apart by code, order and line, so the same picks always give the same route. "Odd aisles up, even aisles down" is read as "the first, third… aisle visited", because aisles are letters.
- **Shared by stretches** (option 5). `PickListSplitter` cuts the route into consecutive pieces of about the same number of stops, the first ones taking the extra when it does not divide evenly. There are never more lists than stops. The supervisor chooses how many (1 to 50); who walks each list is decided with picking.
- **Orders decide whether they join.** A wave asks each order to join (`Order::joinWave`), and the order refuses unless it is allocated, or partially allocated with the backorder accepted (OUT-06). The customer's acceptance is recorded by `accept-backorder`. An order is in one wave only (OUT-05), and all the orders of a wave come from one warehouse (OUT-14).
- **After the release the orders cannot change** (OUT-07). While a wave is planned, cancelling an order takes it out of the wave. Once released, its units are on pick lists, so cancelling is refused until the wave is cancelled. Allocating an order that is in a wave is refused as well.
- **Cancelling a wave frees the orders, not the stock.** Its pick lists are cancelled and its orders go back to allocated or partially allocated, with their reservations intact, ready to join another wave. Releasing the stock is done by cancelling the order (OUT-04).
- **Limits** (200 orders per wave, 500 lines per order, 50 lists per release) keep one request from loading an unbounded amount of data.

## Consequences

- A release reads every reservation of the wave and one location per stop, in one request. It is fine for the size this project targets; a very large wave would need that work done in batches or by the worker (ADR 0012).
- The route follows the pick sequence that Warehouse keeps, not real distances. If the sequences are wrong, so is the route. The aisle only decides the direction.
- Two operators get consecutive stretches of the route, so they meet at the boundary aisle. Balancing by time or by aisle is not attempted.
- A wave is not completed yet: that needs the picks to be confirmed (OUT-08), which arrives with picking, together with starting a list and assigning it to one operator (OUT-12).
- The event board still shows a policy. The code does the same work inside the command, and the board notes it.
