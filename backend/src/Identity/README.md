# Identity

Who may use the system, and for which tenant: tenants, users, their roles and signing in.

| Layer | Holds |
|---|---|
| `Domain` | `Tenant`, `User`, the value objects `Email`, `PlainPassword`, `PasswordHash`, `TenantName`, the `Role` enum, and the ports `UserRepository`, `TenantRepository` and `PasswordHasher` |
| `Application` | Use cases to register a tenant with its first administrator, register a user, and disable or enable one |
| `Infrastructure` | Doctrine mapping and repositories, the security layer (user provider, password hasher, JWT and access denied handlers, the `CurrentTenant` implementation), `GET /api/me`, and the console commands |

Identity depends on nothing, and nothing depends on it. The other contexts learn the tenant of a request through the `CurrentTenant` port in `Shared`, which this context implements from the signed-in user.

Model: [aggregates and invariants](../../../docs/domain/aggregates.md#identity). Decision: [ADR 0008](../../../docs/adr/0008-jwt-authentication-and-tenancy-from-the-signed-in-user.md). API: [authentication](../../../docs/api/authentication.md).
