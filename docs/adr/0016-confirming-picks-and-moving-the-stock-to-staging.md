# 0016. Confirming picks and moving the stock to staging

- Status: Accepted
- Date: 2026-10-24

## Context

A pick list says where to go and what to take ([ADR 0015](0015-waves-and-pick-lists-in-serpentine-order.md)). Confirming it is where reserved stock stops being a promise and becomes goods on a cart. Several things had to be decided:

- **When the stock moves.** The event board has a policy that moves the units to staging when the whole list completes.
- **What happens to the reservation** when its units leave the shelf. The rule that promised units cannot be transferred (STK-02) would refuse the move.
- **What a short pick does**, when the operator finds fewer units than the system said, or none.
- **How a scan is checked**, since the operator scans a barcode and the list holds a product identifier.
- **Who can work a list**, and what an order or a wave may still do once picks are confirmed.

## Options considered

1. **Move the units when the list completes** (the board). One transfer per list, but for as long as the list is open the system shows stock on shelves that is already on a cart, and a second allocation could plan on it.
2. **Move the units at every confirmed stop.** More transfers, but the stock is always where it really is.
3. **Keep the reservation on the shelf item** and transfer anyway, with a special exception to STK-02. It weakens the rule that protects promised units everywhere else.
4. **Move the promise with the units**: unreserve on the shelf, transfer, reserve at staging, and point the reservation at the new stock item. No exception to STK-02.
5. **On a short pick, adjust the shelf down** to what the operator found, and reallocate at once from other stock. It repairs the data, but it trusts one scan to rewrite the ledger, and the new units would need a new pick list.
6. **On a short pick, give the units up** and report them. The shelf is not changed, and the shortage is visible.

## Decision

- **Every confirmed stop is one transaction** (options 2 and 4): the stop on the list, the stock, the order line and, when it is the last stop, the list and the wave. `ConfirmPickLine` calls `StockReservations::pick()`, which unreserves the units on the shelf item, transfers them to a stock item at the staging location of the warehouse (the first active one in the order of the picking route), reserves them there and moves the allocation of the reservation to the new stock item (`StockReservation::shift`, STK-15). STK-02 is untouched, and the move is a transfer of the ledger, so the read model follows it (ADR 0013). The reservation keeps pointing at units that exist and are promised until dispatch consumes them.
- **A short pick gives the units up** (option 6). The units not found stop being promised and the allocation of the reservation shrinks. The order line is allocated for that much less, so its shortage and the backorder of the order grow (OUT-03), and `ShortPickReported` is published. The shelf is not adjusted: an empty shelf and a misplaced pallet look the same from a scan, and the ledger changes only through a movement someone answers for. Counting that shelf is a task for later; until then it can be allocated again and fall short again.
- **Reallocating after a short pick is left to a supervisor**, who allocates the order again and puts it in another wave. Doing it automatically needs a new pick list inside a wave already on the floor.
- **Scans** (OUT-11). The operator sends the location label, the barcode and, if the line has one, the lot. The barcode is looked up in the catalog (any GTIN-8, 12, 13 or 14 of the same item finds the product), and the domain compares the product, not the text. A barcode that is invalid or unknown is a mismatch, not a broken request: it is a mistake on the floor.
- **Who works a list** (OUT-12). A supervisor can assign a list until it is started. An operator who starts one nobody was assigned takes it. Only that operator confirms its picks. Operators are referred to by user identifier, which Outbound does not check against Identity (ADR 0003).
- **After the first pick** the list cannot be cancelled, and neither can its wave (OUT-18). A picked order cannot be cancelled either: its goods are off the shelves. Cancelling a wave whose lists are only started is still possible.
- **A wave completes by itself** when its last list does (OUT-08), and an order is picked when everything still allocated to it has been picked.

## Consequences

- The units at staging are in a stock item like any other, reserved by the order. Packing and dispatch (E04-04) start there, and dispatch consumes the reservation.
- Each pick makes two movements and two events for the read model. A list of fifty stops makes a hundred. It is fine for a floor, and the events can go through the worker (ADR 0012).
- A short pick leaves the system with units on a shelf that may not be there. It is a known gap, and it is the next thing a count should fix.
- A warehouse with no active staging location cannot be picked. Layouts need at least one.
- Returning picked units to the shelf, for a cancelled order, is not built.
- The event board still shows a policy that moves the stock when the list completes. The code does it per stop, and the board notes it.
