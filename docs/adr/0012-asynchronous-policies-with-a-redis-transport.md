# 0012. Asynchronous policies with a Redis transport

- Status: Accepted
- Date: 2026-10-21
- Supersedes the first decision of [ADR 0011](0011-putaway-and-stock-booked-by-event-policies.md) (policies in the command's transaction)

## Context

Since E03-04 closing a receipt books its stock and plans its putaway, and confirming a putaway moves the stock. ADR 0011 ran those policies inside the command's transaction: simple and consistent, but the operator waits for all of it, and a policy that fails (a bug, a lost database connection) undoes the work at the dock and answers with an error. E03-05 asks for asynchronous processing with Redis, a worker in Docker, retries with backoff and a queue for what keeps failing.

Moving a policy out of the request raises four questions:

1. When does an event leave? If it leaves before the command commits, a worker can act on data that is never saved.
2. Messenger runs every handler of a message and, when one fails, retries the message without the handlers that succeeded. If those handlers share one transaction, the retry skips work that was rolled back.
3. A worker can get a message twice (a crash after the commit and before the acknowledgement, a retry). Booking stock twice must be impossible.
4. Where do messages go when the retries run out, and how does someone look at them?

## Options considered

1. **When.** Dispatch at once; dispatch after the command commits (`DispatchAfterCurrentBusStamp`); or write the events to an outbox table in the command's transaction and relay them.
2. **Several policies per event.** Keep them apart and route the event to one transport per policy; or one policy per event, doing everything in one transaction.
3. **Duplicates.** Deduplicate by message id in a table; or make each policy idempotent by recording, in its own transaction, that it ran.
4. **Failures.** A second Redis stream, or a PostgreSQL table through Doctrine's transport.

## Decision

1. **After the command commits.** The event bus stamps every event with `DispatchAfterCurrentBusStamp`: events of a command that fails are dropped, and events of one that commits are sent afterwards. An outbox would also survive Redis being down at that moment; it arrives with the ERP integration (E08-04), which needs it anyway.
2. **One policy per event.** `ProcessClosedReceipt` books the stock and plans the putaway of a closed receipt; `MoveStockOnPutaway` moves the stock of a confirmed task. Each runs in its own transaction (the event bus has `doctrine_transaction`), so a failure rolls back the whole policy and a retry does it all again.
3. **Idempotent policies.** The receipt records when its stock was booked and the task when its stock moved, in the same transaction as the work. A policy that finds the mark does nothing. The API shows both (`stockBooked`, `stockMoved`), so a client can tell the work is done.
4. **Retries and a failure table.** `ReceiptClosed` and `PutawayConfirmed` are routed to `async`, a Redis stream (`symfony/redis-messenger`). A failed message is retried after 1, 3 and 9 seconds, then kept in the `failed` transport, a PostgreSQL table (`symfony/doctrine-messenger`), where `make failed` lists it and `make retry-failed` handles it again. A Redis stream cannot list its messages, which would leave failures out of sight.

The worker is a Docker service running `messenger:consume async`. It stops every hour or at 256 MB and Docker starts it again, so a long-running PHP process never grows stale; after changing code, `make worker-restart`. The tests replace the async transport with a synchronous one, so a policy runs right after its command commits, as a worker would.

## Consequences

- Closing a receipt or confirming a putaway answers at once. The stock and the putaway tasks appear a moment later: clients read `stockBooked` and `stockMoved`.
- A failing policy no longer undoes the work at the dock. It is retried, and after that it waits in the failure table for a person, with nothing lost.
- Between the commit and the send, Redis being down loses the event; the request then answers with an error although the command committed. The outbox of E08-04 closes that gap.
- Events published outside a command, or not routed to `async`, still reach their handlers at once.
- Every new policy that can be routed to `async` must be idempotent, and an event must carry what its policy needs, because the policy runs without the request (no signed-in user, no tenant filter).
