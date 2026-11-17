# 0062. Structured logs and two health checks

- Status: Accepted
- Date: 2026-11-17

## Context

The application wrote its logs with the default logger of Symfony: free text on the standard error, with nothing to say which request a line belonged to. Looking for what happened to one request meant reading everything around it, and the worker, which handles many messages in one process, was worse.

There was one health check, `/health`, which looks at PostgreSQL and Redis. It answers the question "can the application do its work", and a platform that uses it to decide whether to restart a container restarts it when the database is slow, which does not help. The images had no `HEALTHCHECK`, so Docker could not tell a stopped server from a running one.

The plan for this task also said Sentry and OpenTelemetry. The project is not deployed (no hosting, no public demo), so there is nowhere to send errors or traces, and a service that nothing reads is not observability. They are left out.

## Options considered

For the logs:

1. **Our own PSR-3 logger** that writes JSON. No dependency, but formatting, levels, channels and the handlers of tests would be ours to keep.
2. **`symfony/monolog-bundle`.** The standard of Symfony: a JSON formatter, processors that add fields to every line, and channels. A new dependency, approved for this task.

For the health checks:

1. **One endpoint for everything.** What it is today.
2. **`/health/live` and `/health`.** Liveness looks at nothing but the process. Readiness is the check that already exists.

## Decision

**Logs.** Option 2. Everything goes to the standard error, which is where Docker reads it. In production each line is a JSON object; in development it stays readable text. `LogContextProcessor` puts `request_id` in the `extra` of every line, and `user_id` and `tenant_id` when somebody is signed in or a job runs for a tenant. Only identifiers go there: an email address is personal data and is never written to a log. Its ports are lazy, since the security layer logs too and would otherwise wait for itself.

**Correlation.** `RequestIdListener` gives each request an identifier before the firewall runs and returns it in the `X-Request-Id` header, on error answers too. A caller that sends its own keeps it, if it has 8 to 64 plain characters (letters, digits, `.`, `_`, `-`); anything else is replaced, because a value with a line break would write a line of its own in the log. The worker takes a new one for each message it receives.

**Health.** Option 2. `/health/live` answers 200 and touches nothing. `/health` is unchanged: 200 with the checks, or 503 saying which one is down. Both pass through the proxy of the frontend image. The production images of the API and of the frontend now have a `HEALTHCHECK`, and the API one asks `/health/live`.

## Consequences

- A line of the log can be tied to its request, its user and its tenant, and a person who reports an error can give the `X-Request-Id` of the answer.
- The check of Docker restarts nothing because of a slow database. Whoever reads `/health` (a platform, CI) still learns that the dependencies are down.
- The development worker still has no healthcheck: it is a consumer with no port, and Docker restarts it when it stops (ADR 0012).
- The log line is not yet a schema we promise to keep. `extra.request_id`, `extra.user_id` and `extra.tenant_id` are the fields to rely on.
- This replaces the sentence of ADR 0052 that said the images have no `HEALTHCHECK`.
- If the application is ever deployed, Sentry or an OpenTelemetry collector is a handler or an exporter to add next to these, with the identifier already in every line.
