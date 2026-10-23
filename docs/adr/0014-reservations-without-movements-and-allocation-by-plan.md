# 0014. Reservations without movements, and allocation by plan

- Status: Accepted
- Date: 2026-10-23

## Context

An order needs stock to be promised to it before anyone picks anything. Two orders must never be promised the same units, and a promise must be taken back when the order is cancelled. Three things were open:

- **Where the promise lives.** The ledger records every change of `onHand` and is the only way stock changes (invariants STK-03 to STK-05). A promise moves no units.
- **Who decides which stock is promised.** FEFO and FIFO (STK-12) need the whole picture of a product's stock, which Outbound does not have and must not have (ADR 0003, DD-3).
- **How two requests that reserve at once are kept apart.** Checking `reserved <= onHand` in memory is not enough when two requests read the same row.

## Options considered

1. **A reservation is a movement of the ledger.** One mechanism for every change. It would put rows in the ledger that change no unit, and break STK-05, where the movements add up to `onHand`.
2. **`reserved` is a number on the stock item and nothing else.** Simple, but releasing a reservation would not know which stock items to free, and an order could not tell what holds its units.
3. **`reserved` on the stock item, and a reservation aggregate that remembers its allocations.** More to build. A release knows exactly what to give back, and the ledger stays a record of units.
4. **Pessimistic locks (`SELECT ... FOR UPDATE`) while allocating.** Safe, but a long allocation of a popular product would hold up every other order, and the locks are easy to forget in a new code path.
5. **Optimistic locking with a version on the stock item.** Nothing is locked while the request thinks. The second request to save is refused, and asking again works.

## Decision

Options 3 and 5.

- **`StockItem` holds `reserved`.** `available = onHand - reserved`. Reserving and unreserving change `reserved` only and record no movement and no event. Everything that takes units out (issue, negative adjustment, transfer) is refused when it would cut into `reserved` (STK-02), so promised units are not given away. A database `CHECK (reserved >= 0 AND reserved <= on_hand)` backs it.
- **`StockReservation` is an aggregate of Inventory.** It holds the reference of what it serves, the product, the quantity asked, the strategy and the allocations. A released reservation holds nothing, and releasing it again gives nothing back (STK-14). There is one reservation per reference and tenant, backed by a unique index.
- **The reference is opaque.** Outbound uses `order:<orderId>:<lineNumber>`; Inventory never looks inside it. Asking again with the same reference tops the reservation up to the quantity asked at first, so an allocation is safe to repeat and a partly covered order can be completed when stock arrives. The quantity cannot change between requests.
- **Allocation is a plan.** `AllocationPlanner`, a pure domain service, chooses the stock items from the candidates: FEFO for products tracked by expiry date, FIFO for the rest, skipping stock that would expire before the shelf life the product promises at shipment. The application layer (`LedgerStockReservations`) finds the candidates, reserves from each one in the plan and records the allocations. Only stock in an active reserve or pick face location of the order's warehouse is offered (STK-06): stock at a dock has not been put away.
- **Outbound asks through a contract.** `StockReservations::allocate()` and `release()` take and return plain values. A shortage is a result, not an error (STK-11): the order becomes partially allocated and reports the units it lacks.
- **The version.** Every save of a stock item raises its version. A request that read an old one is refused (`OptimisticLockException`), answered as `shared.concurrent_update` with status 409. The whole command rolls back, so a half-made allocation never remains.
- `FIFO` needs to know when a stock item first got units. The stock item keeps it (`firstReceivedAt`); the migration fills it from the ledger for the stock that already existed.

## Consequences

- The ledger stays a record of units and STK-05 holds. To see what is promised, read the stock items and the reservations.
- The stock read model (ADR 0013) follows movements, and a reservation records none, so `GET /stock-items` does not show `reserved` or `available` yet. The order shows what it holds. Showing it in the stock queries needs the stock item to publish an event when units are reserved or freed, for the read model to follow. That is a follow-up.
- A client that gets a 409 on an allocation retries it. Doing so is safe because allocating is idempotent.
- Allocation reads every reservable stock item of the product. It is fine for the size this project targets, and a product with thousands of stock items would need a query that stops early.
- Consuming a reservation when the goods are picked and shipped (STK-13) and a reservation that expires are not built. They come with picking.
- Allocation is asked for explicitly (`POST /orders/{id}/allocate`). Doing it automatically when an order is registered, or when stock arrives, is a policy that can be added on top of the events (ADR 0011) without changing this.
