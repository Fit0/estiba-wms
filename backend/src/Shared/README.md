# Shared

Code that no single bounded context owns. Keep it small: if something belongs to a context, it lives there.

| Layer | Holds |
|---|---|
| `Domain` | Building blocks every context uses, such as the domain event interface and the tenant identifier |
| `Infrastructure` | Cross-cutting adapters, such as the `/health` endpoint |

`Shared` must not depend on any bounded context. Contexts may depend on `Shared`.
