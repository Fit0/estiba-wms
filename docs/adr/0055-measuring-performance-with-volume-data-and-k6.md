# 0055. Measuring performance with volume data and k6

- Status: Accepted
- Date: 2026-11-15

## Context

E10-06 asks for indexes, a look at N+1 queries, HTTP caching and a load test. The demo has 39 products and no history, so every query is fast and nothing can be seen. A change to an index made without a measurement is a guess, and a guess in a portfolio project is worse than no change: it adds a migration that a reviewer has to trust.

## Decision

**Measure first, on data of a realistic size.** `perf/seed.sql` adds 20 000 products with one stock line each, 500 000 ledger movements (25 per line) and 20 000 orders to a development database. It writes straight to the tables with SQL: going through the domain would take hours and would measure the seed. It is not demo data and not a fixture, it lives outside `backend/`, and no code depends on it. Everything it writes is marked (`PERF-` in references and SKUs, the reference `PERF` in movements), so it can be replaced.

**`perf/explain.sql` prints the plan of each list query** as the application sends it (`EXPLAIN (ANALYZE, BUFFERS)`), so the plans before and after an index can be put side by side.

**k6 runs in Docker** (`grafana/k6`), from a script in `perf/k6/`. It is a tool, not a dependency of the project: nothing in `composer.json` or `package.json` changes, and it is not installed on the machine. The sign-in happens once, in `setup()`, since the limiter of ADR 0054 would otherwise be what the test measures. Thresholds are the budget of the API and make the command fail when they are not met.

**An index is added only when a plan shows the need**, and its migration says which query it serves.

## Consequences

- The figures are those of a laptop where the API, PostgreSQL and k6 share the machine. They are good for comparing, not for promising a latency.
- The ids of the seed are random UUIDs, not UUID v7. For these queries it makes no difference, and it is said in the seed.
- The ledger of each seeded line adds up to its stock, but the seed does not claim more: `make stock-rebuild` on this data is not expected to give back the demo figures.
- There is no load test in CI. A run takes minutes and its numbers depend on the runner. The commands are kept so a reviewer can repeat it.
