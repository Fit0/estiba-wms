# Performance measurements

How the API is measured, and what it has to meet. The decision is in [ADR 0055](../docs/adr/0055-measuring-performance-with-volume-data-and-k6.md).

## Steps

You need a development stack with the demo loaded (`make up`, `make demo`).

```bash
make perf-seed                       # 20 000 products, 500 000 movements, 20 000 orders (about a minute)
make perf-explain > perf/before.txt  # the plan of each list query
make perf-load PASSWORD='...'        # k6 against http://localhost:8080 with the demo password (development stack)
```

The development container is slow for reasons that have nothing to do with the code (debug kernel, bind mount). For latency, measure the production image: `make perf-prod-up`, then `make perf-load PASSWORD='...' URL=http://host.docker.internal:8082`, and `make perf-prod-down` when done. Do the same again after a change and compare. `perf-seed` can be run again: it replaces what it wrote, and it never touches the data of `make demo`.

## What the load test asks

One signed-in supervisor reads the lists an office user opens all day: orders by status, a deep page of orders, a product search, the stock, the ledger of movements (all of it, and one product's) and the KPIs. By default 3 users for a minute and a half, which is what the budget is checked with. `VUS=20` pushes the API until it queues, and is how the capacity of the machine is read: it is expected to cross the thresholds. The sign-in happens once, before the test, because it is limited to a few tries (ADR 0054).

| What | Budget (95 % of requests) |
|---|---|
| Orders by status, first page | 400 ms |
| Orders, page 600 | 600 ms |
| Product search by text | 400 ms |
| Stock of the tenant | 400 ms |
| Movements, all and of a product | 600 ms |
| KPIs of a quarter | 800 ms |
| Requests that fail | under 1 % |

The budget is a first line, not a promise: the numbers are taken on a laptop with Docker, with the API, the database and k6 sharing the same machine. What counts is the comparison before and after a change.

## Results

See [docs/performance/results.md](../docs/performance/results.md).
