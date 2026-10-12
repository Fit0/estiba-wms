# 0003. Cross-context communication through published contracts

- Status: Accepted
- Date: 2026-10-12

## Context

The contexts defined in ADR 0001 need each other. Inbound puts goods into stock, so it must tell Inventory. Outbound allocates stock, so it must ask Inventory what is available. Everything refers to products defined in Catalog and locations defined in Warehouse.

If a context imports another context's entities, the boundary exists only on paper. A change inside one context then breaks another, and the contexts cannot be understood or extracted separately.

## Options considered

1. **Use the other context's domain classes directly.** Simple, but it couples the models and makes the boundary meaningless.
2. **Join across the other context's tables.** Fast to write, but it couples the database schemas.
3. **Each context publishes a small contract, and other contexts depend only on it.** Calls go through an interface and data objects. State changes travel as integration events.

## Decision

Option 3.

- Every context has an `Application/Contract/` namespace. It holds the public interfaces, data transfer objects and integration events of that context.
- A class in one context may depend on another context only through that `Application\Contract` namespace. It may never depend on the other context's `Domain`, `Infrastructure` or the rest of `Application`.
- Contexts reference each other's data by identifier value (for example a `ProductId`), not by object reference, and there are no foreign keys between tables of different contexts.
- Integration events are dispatched through Symfony Messenger. They start in-process and synchronous, and can move to Redis transport when a flow needs to be asynchronous (E03).
- `Shared` may be used by every context and must not depend on any of them.

The rule is enforced by `ArchitectureTest::testContextsOnlyMeetThroughPublishedContracts()`, which generates one rule per pair of contexts.

## Consequences

- Each context can change its internals freely, as long as its contract stays stable.
- Cross-context data is eventually consistent when it travels by event. Flows that need a strong guarantee use a synchronous contract call instead.
- Queries that combine data from several contexts need a dedicated read model. They cannot join tables directly (see the stock read model in E03).
- A contract is an extra class to write and keep compatible, which is a deliberate cost.
