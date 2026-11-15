# Performance results

Measured with the steps in [perf/README.md](../../perf/README.md), on a laptop with Docker where the API, PostgreSQL 17 and k6 share the machine. Data: 20 000 products, 20 000 stock lines, 500 000 ledger movements and 20 000 orders (`make perf-seed`).

## 1. The plans of the list queries (`make perf-explain`)

Execution time as PostgreSQL reports it, before and after the index of [ADR 0056](../adr/0056-an-index-for-the-latest-movements.md).

| Query | Before | After | Plan after |
|---|---|---|---|
| Orders of a status, most urgent first | 13 ms | | top-N sort, unchanged |
| Orders, page 600 | 62 ms | | sort that spills to disk, left as it is |
| Products that contain a text | 0.4 ms | | unchanged |
| **Latest movements of the tenant, the page** | **141 ms** | **2.3 ms** | backward scan of the new index |
| Latest movements, the count | 92 ms | 81 ms | the ledger is counted whole, left as it is |
| Movements of a stock line | 0.4 ms | | unchanged |
| Movements of a product | 2.1 ms | | unchanged |
| **Movements in a location** | **51 ms** | **3.9 ms** | uses the new index |
| Stock of the tenant | 7.7 ms | | unchanged |
| Stock of a product | 0.07 ms | | unchanged |

What was looked at and not changed:

- **N+1**: there are none. No entity has a Doctrine relation: the lines of an order, a pick list and a shipment are JSON columns, and the providers of the lists only transform what they receive.
- **Search of products by text** (`LIKE '%text%'`): 0.4 ms. A trigram index would be worth it with a catalogue a hundred times bigger, not with 20 000 products.
- **Count of the ledger**: counting is O(n). At 500 000 movements it costs about 80 ms, and it is the price of an exact `X-Total-Count`.
- **Page 600 of the orders**: the order is a computed `CASE` on the priority, which no index serves. 62 ms at 20 000 orders.

## 2. Load test on the development stack

20 users, 1 minute 40 seconds, `make perf-load VUS=20` against `http://localhost:8080` (APP_ENV=dev, classic mode, code through a bind mount): about 8.6 requests per second and a p95 of 1.8 s to 2.4 s on every endpoint, with 0 % of failures.

Every endpoint costs the same, including the cheapest, and the database answers in under 15 ms. It measures the environment (the kernel boots in debug mode on every request, and opcache checks the files each time), not the queries. These figures are not used as a budget.

## 3. Load test on the production image

`make perf-prod-up`, then `make perf-load URL=http://host.docker.internal:8082`. The image is the one `make images` builds, over the same database.

**Budget, 3 users** (a light office load, the default). All the thresholds pass, with 0 % of failures:

| Endpoint | avg | p95 | Budget |
|---|---|---|---|
| `/health` (the floor: no database, no sign-in) | 18 ms | 24 ms | |
| Orders by status | 72 ms | 80 ms | 400 ms |
| Orders, page 600 | 125 ms | 147 ms | 600 ms |
| Product search by text | 78 ms | 94 ms | 400 ms |
| Stock of the tenant | 57 ms | 67 ms | 400 ms |
| Movements, all | 113 ms | 137 ms | 600 ms |
| Movements of a product | 53 ms | 63 ms | 600 ms |
| KPIs | 70 ms | 86 ms | 800 ms |

**Capacity, 20 users** (`VUS=20`): 31.6 requests per second, 0 % of failures, and a p95 between 357 ms and 769 ms, with the four slowest endpoints above their budget. With 20 users sending a request every second on a laptop that also runs PostgreSQL and k6, requests queue for a free PHP thread: the same endpoints answer in 80 ms to 150 ms when they do not wait. This run reads the capacity of the machine, and it is expected to cross the thresholds.

Reading the two together: what a request costs is 50 ms to 125 ms on top of a floor of 18 ms, and for the two slowest (movements, page 600 of the orders) the database takes the larger share: the count of the ledger and the sort of the orders. Nothing else is left that a measurement justifies changing.

## Left as it is, on purpose

- **`GET /api/pick-lists` is not paged.** It is read with `waveId` by the screens, and a wave holds at most 50 lists (OUT-16). Without the filter it returns every list of the tenant. Paging it changes the contract of the API and the picking screens, for a list nobody reads whole. It goes in the list of known limits.
- **No ETag on the lists.** A 304 saves the body, not the work: the server still runs the query. The lists are private and change with every task. See [ADR 0057](../adr/0057-http-caching-of-the-api.md).
