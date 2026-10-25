# 0018. Audit trail of every domain event

- Status: Accepted
- Date: 2026-10-25

## Context

A warehouse is asked who did what: who confirmed that pick, who blocked that location, when a parcel was closed. The domain already tells what happens, as events named in the past tense. They go to the policies after the change commits, and then they are gone. Several things had to be decided:

- **What is written**: some events, chosen one by one, or every event.
- **Where and when it is written**, so that it neither misses a change nor invents one.
- **Who did it**, when many events do not say, and when a worker handles a policy with nobody signed in.
- **Which tenant it belongs to**, since many events do not carry one.
- **What stays out**, since events carry email addresses.
- **Where it lives** in a design where contexts only meet through contracts.

## Options considered

1. **Each use case writes its own audit line.** Precise, but every new use case must remember to, and a forgotten one leaves a hole nobody sees.
2. **A policy (an event handler) that listens to every event**, in its own transaction. Simple, but it runs after the commit: a crash between the commit and the policy loses the line, and an asynchronous event would be written by a worker where nobody is signed in.
3. **A decorator of the event bus** that writes the line when the event is published, in the transaction of the publisher.
4. **A table written by database triggers.** It cannot miss a change, but it knows rows and not events, and loses the meaning that the domain gave them.
5. **An allow-list of fields per event** to keep personal data out. Safe, and a chore for every new event.
6. **A `#[Sensitive]` attribute** on the fields to leave out, with everything else copied.

## Decision

- **Every event, by a decorator of the event bus** (option 3). `AuditingEventBus` wraps the bus and, for each event, asks `AuditTrail` to write a line before the event goes on. The line is staged with the unit of work, so it is committed with the change that caused it, or not at all (AUD-02). A command that is refused publishes nothing. A new event is audited with no extra code.
- **The event is described by reflection** (`EventDescriber`): its type comes from where the class lives (`App\Outbound\Domain\Event\PickListStarted` is `outbound.pick_list_started`), its subject is its first identifier, which in every event is the aggregate it is about, and its data are its public fields turned into plain values.
- **A bounded context of its own, `Audit`**, that depends on nothing and that nothing depends on. It reads the events through the bus and not through the contexts, so the rule that contexts only meet through contracts holds, and the architecture test says so.
- **The actor is the signed-in user** (`CurrentUser`), and nobody when a policy runs in a worker. That is the truth: an asynchronous policy acts on its own, caused by an event whose own line already says who.
- **The tenant is the one the event carries**, and when it carries none, the one of the signed-in user. Many events (Catalog, Inbound, Warehouse) carry no tenant, but those are published by a request. An event with neither is **reported in the log and not written**: failing would undo a change that is valid. Today none is in that case.
- **Personal data stays out with `#[Sensitive]`** (option 6). The email of a user, in the three events of Identity, is marked and the trail never sees it. The marker is on the event itself, so whoever adds a field sees it.
- **Reading** is `GET /api/audit-log`, paged, the latest first, with filters by event type, actor, subject and time, for supervisors and admins. Operators see the floor, not the history of who did what. There is no way to write, change or delete through the API (AUD-01).

## Consequences

- Every change writes one line per event: a pick makes about five (the confirmation, two stock movements, and the order and list changes). The trail grows with the business. It has indexes for the filters, and a retention policy or partitioning by month is the next step when it is large; it is not built.
- The trail is as honest as the events are. A change that does not publish an event is not in it. The domain publishes the events of every rule that matters, but a new rule needs its event.
- A line is written in the same transaction as the change, so a slow audit slows the change. It is one insert.
- There is no protection against someone with access to the database. A chain of hashes between lines would show tampering; it is for later, if a customer needs it.
- Personal data in a field not marked `#[Sensitive]` would be kept. A test covers the three events that carry an email, and a reviewer must look for the attribute whenever an event gets a field about a person.
- A line's `data` is the event as it was. If an event changes its fields, old lines keep the old shape.

## Update of 2026-10-25: events that were never published

Writing the end-to-end test of E04-06 showed that the trail was missing events as basic as the creation of a warehouse or the registration of an ASN. The cause was not the trail: **25 use cases of Catalog, Warehouse, Identity, Inbound and Outbound, and one policy, raised events in their aggregates and never published them.** Nothing had noticed because nothing listened to those events. The decision above ("a new event is audited with no extra code") held only for a use case that published.

What changed:

- **They publish now.** The 25 use cases and the policy that plans putaway tasks publish the events of the aggregates they change.
- **A guard makes the omission fail** (`PendingEventsGuard`). In development and in the tests, a middleware of the command bus and of the event bus checks, when a use case or a policy ends, whether any aggregate it loaded or created still holds events nobody took, and fails with the aggregate and the events. It is not active in production, where it would turn an omission into an error for the user. Fixtures that build an aggregate by hand take its events (`pullEvents()`) to say that they are not for anybody.
- **An event names its tenant.** A policy runs in a worker with nobody signed in, so an event it publishes without a tenant would reach the trail with no way to know whose it is, and would only leave a warning in the log. `PutawayTaskPlanned`, which a policy publishes, was one: it carries its tenant now. A test (`EventsCarryTheirTenantTest`) makes a new event carry its tenant, and lists the 22 older ones that do not, which are published only by requests. The list can only shrink.
