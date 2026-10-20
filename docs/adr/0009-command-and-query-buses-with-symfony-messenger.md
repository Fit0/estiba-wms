# 0009. Command and query buses with Symfony Messenger

- Status: Accepted
- Date: 2026-10-21

## Context

Until now a processor called a handler and flushed the entity manager itself ([ADR 0006](0006-http-api-with-api-platform-and-dto-resources.md)). That works for one entry point, but it leaves the transaction to every caller: the API, the console commands, and soon the asynchronous workers of E03-05. A caller that forgets to flush loses the change, and one that flushes too early commits half of a use case.

Reads and writes also have different needs. A write needs a transaction, and a failed write must undo everything it did. A read needs none, and must not be able to change state by mistake.

## Options considered

1. **Keep calling handlers directly.** Nothing new to learn. The transaction stays the caller's job, and the same use case cannot move to a worker without being rewritten.
2. **One Messenger bus for everything.** Less configuration. A query would run in a transaction it does not need, and nothing would stop a query handler from changing state.
3. **Two buses, `command.bus` and `query.bus`.** A little more configuration. Each kind of message gets the middleware it needs, and a handler can only be reached through the bus it was registered on.
4. **A hand-written bus.** No dependency. It would repeat what Messenger already does (middleware, retries, transports) and still need those for E03-05.

## Decision

Option 3, with `symfony/messenger`.

- **Two ports in `Shared/Application/Bus`**: `CommandBus::dispatch(Command)` and `QueryBus::ask(Query)`. The Application layer only knows these interfaces and the markers `Command`, `Query`, `CommandHandler` and `QueryHandler`. Messenger lives in `Shared/Infrastructure/Messenger`, so the use cases import nothing from Symfony.
- **Handlers are registered by interface, not by attribute.** `config/services.yaml` tags every `CommandHandler` for `command.bus` and every `QueryHandler` for `query.bus`; Messenger finds the message from the type of the argument of `__invoke()`. One handler per message.
- **`command.bus` runs the `doctrine_transaction` middleware.** The transaction opens before the handler, the entity manager is flushed when it returns and the transaction is committed. If the handler throws, the transaction is rolled back. Handlers and repositories therefore never flush. `query.bus` has no transaction.
- **A domain error reaches the caller as it was thrown.** Messenger wraps whatever a handler throws in a `HandlerFailedException`. The bus adapters unwrap it, so `ApiErrorListener` still sees a `DomainError` and answers with its translated problem ([ADR 0007](0007-one-translated-problem-shape-for-every-api-error.md)).
- **A command may return what it created or changed.** Strict CQRS has commands return nothing, and the caller then runs a query. That would double the round trips and the number of classes for every endpoint. Here the processor needs the aggregate to build its response, so the handler returns it. The result is typed (`Command<Product>`), and a query never changes state.
- **Entry points use the buses.** Processors, providers and console commands send a command or ask a query. The architecture test fails any class in `Infrastructure` that depends on a handler, and any handler that is not final, read-only and invokable.
- **No transport yet.** Everything is handled in the same request. Redis and asynchronous handling arrive with E03-05, where the bus needs no change.
- Domain events stay on their aggregate for now. Publishing them after the commit is part of E03-05.

## Consequences

- A use case runs the same from the API, the console or a worker, and it is atomic wherever it runs.
- A new use case is a `Command` or `Query` class and a `...Handler` that implements the marker. There is no wiring to write.
- A handler that must write twice, such as a transfer between two stock items, does so inside one transaction without any extra code.
- Nested dispatches (a handler dispatching another command) join the open transaction. Doing it on purpose is rare and should be explained where it happens.
- The tests of the buses use their own messages (`tests/Functional/Shared/Messaging/Fixtures`), registered only in the `test` environment.
- Costs: more classes per use case, and a debugging path that goes through middleware. `php bin/console debug:messenger` lists every message with its handler.
