# 0045. Live notifications with Mercure

- Status: Accepted
- Date: 2026-11-04

## Context

The dashboard (ADR 0044) asks the API every 30 s. A supervisor should also hear it when something happens that needs a look: a pick list was completed, an operator did not find some units. The CLAUDE.md table has planned Mercure for this since the start; FrankenPHP, which already serves the API, has a Mercure hub built in.

## Options considered

1. **Polling faster.** No new moving part, but every open screen asks all the time and still hears late.
2. **WebSockets.** Two ways, so the browser could also send; this is one way only, and a socket server is one more service.
3. **Server-sent events through the Mercure hub of FrankenPHP.** One-way, reconnecting by itself in every browser (`EventSource`), authorized with a JWT, and no new service in `compose.yaml`.

## Decision

Option 3, in these terms.

- **Policies announce, the hub delivers.** `AnnouncePickListCompleted` and `AnnounceShortPick` are event handlers (policies) in Outbound. They call the `Notifier` port of `Shared`, whose implementation, `MercureNotifier`, posts a private update to the hub. A command never waits for the hub: the two events are routed to the `async` transport, so a hub that is down is retried by the worker (ADR 0012) and never fails a pick.
- **The id of a notification is the fact.** `pick-list-completed:<id>` and `short-pick:<id>:<position>`. A retry sends the same id, and the screen shows an id once. That is the idempotency these asynchronous policies need, with no table of their own.
- **One topic per tenant, private.** `/tenants/<tenantId>/notifications`. The publisher token may publish there and the subscriber token of a tenant may subscribe there and nowhere else, so a tenant never hears another one (the token is built from the tenant of the signed-in user, never from the request).
- **Access by a cookie the page cannot read.** `GET /api/notifications/access` (supervisor and admin) answers with the topic and the hub and sets the `mercureAuthorization` cookie: `HttpOnly`, `SameSite=Strict`, only sent to `/.well-known/mercure`, one hour long like the access token. The page opens an `EventSource` with credentials and asks for access again five minutes before the end, and with a growing wait (4 s to 1 min) when the stream closes.
- **Two keys.** The publisher key (the API and the worker) and the subscriber key (what the hub checks in browsers' cookies) are different variables, `MERCURE_PUBLISHER_JWT_KEY` and `MERCURE_SUBSCRIBER_JWT_KEY`. `compose.yaml` gives development values; production sets its own.
- **The protocol is spoken directly, with no package.** This work was done where Composer could not reach Packagist, so `symfony/mercure-bundle` could not be added. Publishing is one form-encoded POST and a token is an HMAC SHA-256 signature, a hundred lines in `Shared/Infrastructure/Realtime` with their tests. `Notifier` is the port: replacing the class by the official bundle changes one file and nothing that announces.
- **The hub reads the tokens in compatibility mode.** Recent versions of the Mercure module refuse `publisher_jwt` and `subscriber_jwt` unless the Caddyfile says `protocol_version_compatibility 8`, or uses an `issuer` block (modern mode, with its own rules for the claims). The first was chosen because it keeps the tokens as the API makes them; moving to the modern mode is a later, separate change.
- **A notification is a hint.** It carries identifiers and numbers, no names and no emails. On receiving it the screen shows a notice and asks the API again for the figures and the pick lists, which hold the truth. A supervisor who was not connected learns it from the screens. The updates are kept by the default transport of the hub, in memory, so a restart of the hub forgets them: the audit trail is the record, not this.
- **The 30 s polling stays.** It is the safety net when the stream is down, and it can be made slower later without changing anything else.

## Consequences

- The tests have no hub: `config/services_test.yaml` swaps the `Notifier` for one that remembers, and `HmacJwt`, `MercureNotifier` and the access endpoint have tests of their own. What the tests cannot prove is the hub itself and its Caddy configuration; the way to check them is below.
- A new kind of notification is a policy and a branch in `parseNotification`; the screen ignores types it does not know, so an API newer than the screen does no harm.
- The worker must be restarted after changing a policy (`make worker-restart`).
- In production the frontend and the API must be served from the same origin (or the hub must allow it with `cors_origins`), and the proxy in front must not buffer `text/event-stream` (E10-01).
- Only supervisors and admins listen. The operators' phones do not open a stream: their screens are the picking ones.

## Checking it by hand

With `docker compose up`, signed in as a supervisor:

1. `docker compose logs php` shows no error about `mercure` at start (a wrong Caddyfile would stop the server).
2. In the browser, the network tab shows `access` answering 200 with a `Set-Cookie`, and a request to `/.well-known/mercure?topic=…` that stays pending (type `eventsource`).
3. Without a cookie the hub answers 401 to `curl -N "http://localhost:8080/.well-known/mercure?topic=/tenants/<id>/notifications"`.
4. Complete a pick list from another session (an operator, or the API): within a second the supervisor sees "Hoja de picking completada" and the figures of the home page change.
5. `docker compose logs worker` shows the two policies handled, with no retry.
