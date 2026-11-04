# Analytics

The figures a supervisor looks at to run the warehouse: orders, picking speed, short picks, stock by status and how full the storage locations are.

| Layer | Holds |
|---|---|
| `Domain` | `KpiPeriod`, the period a figure is about |
| `Application` | `GetKpis`, which asks the other contexts for their figures and puts them together |
| `Infrastructure` | The `GET /api/kpis` resource and its state provider |

Analytics owns no table and no aggregate. Each context answers for its own data through a contract (`OutboundKpis`, `StockKpis`, `LocationCensus`) with plain SQL, and this context only adds them up and divides (ADR 0043). Nothing here changes state.
