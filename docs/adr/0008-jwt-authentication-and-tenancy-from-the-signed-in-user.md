# 0008. JWT authentication, and the tenant comes from the signed-in user

- Status: Accepted
- Date: 2026-10-20

## Context

Until now the API trusted a header, `X-Tenant-Id`, to know which tenant a request acted for ([ADR 0006](0006-http-api-with-api-platform-and-dto-resources.md)). Anyone who knew another tenant's identifier could read and change its data. That was a stand-in, and it had to go before the system held real data.

Three questions follow. How does a caller prove who it is? Where does the tenant come from? And what stops a bug from leaking one tenant's rows to another?

## Options considered

**How a caller proves who it is**

1. **Session cookies.** Simple on a web page, but the API is also meant for a PWA, scripts and other clients, and cookies bring CSRF with them.
2. **JWT, trusted as it is.** Stateless and fast, but a token cannot be revoked, and a stolen or stale token keeps its roles until it expires.
3. **JWT, with the user loaded from the database on every request.** The token only says who the caller is. Roles and status are read fresh, at the cost of one indexed query per request.

**Where the tenant comes from**

1. **A header the client sends.** What we had. The client decides, so it is not a boundary.
2. **A claim in the token.** Safe if the token is, but it can go stale, and nothing then ties it to the user.
3. **The signed-in user.** Each user belongs to exactly one tenant for its whole life, and the request takes the tenant from that user.

**What stops a leak**

1. **Every use case checks the tenant.** Already true, but one forgotten check is a leak.
2. **Also a database filter that adds the tenant to every query.** A second wall behind the first.

## Decision

- **A new bounded context, `Identity`.** It owns `Tenant` and `User`, depends on nothing and nothing depends on it. The other contexts only ever see the tenant through the `CurrentTenant` port in `Shared`. Its rules are IDN-01 to IDN-07 in [the aggregates document](../domain/aggregates.md#identity).
- **JWT signed with RS256, user loaded on every request** (options 3). A user is disabled, or its roles change, and the very next request reflects it. A token lasts one hour. The key pair is generated with `make jwt-keys` and never committed; in production the key files and the passphrase come from the environment.
- **The tenant is the signed-in user's** (option 3). `SecurityCurrentTenant` is the only implementation of `CurrentTenant`. No code reads a tenant from a request. `X-Tenant-Id` is gone.
- **Roles include the ones below them:** `admin`, then `supervisor`, then `operator`. Reading needs any signed-in user; changing anything needs a supervisor or an admin. The rule lives in `access_control` in one place, `config/packages/security.yaml`, and is refined per route when a role needs more or less. The roles in a token are not believed: the server reads them from the database.
- **A Doctrine SQL filter adds `tenant_id = <tenant>` to every query on an entity that has a `tenantId` field** (option 2). It is switched on for each signed-in request and off for everything else, and it fails closed: with the filter on and no tenant set, Doctrine throws. Console commands, migrations and the sign-in itself run without it.
- **Passwords** are hashed with Symfony's `auto` algorithm behind the `PasswordHasher` port, so the domain never sees an algorithm. The policy is 12 to 72 characters (72 is bcrypt's limit in bytes). A plain password lives in `PlainPassword`, which cannot be printed, logged or serialized. It is never taken as a console argument: it is asked for hidden, or read from `ESTIBA_PASSWORD`.
- **Failures look the same.** A wrong password, an unknown email and a disabled account all answer `identity.credentials_invalid`, so the answer does not tell a stranger which accounts exist.
- **Errors** go through the common translated problem shape ([ADR 0007](0007-one-translated-problem-shape-for-every-api-error.md)): `shared.unauthenticated`, `shared.token_invalid` and `shared.token_expired` for 401, and `shared.forbidden` for 403.
- **Setting up** is by console for now: `app:tenant:create`, `app:user:create`, `app:user:disable` and `app:user:enable`.

## Consequences

- Reading a tenant's data without being one of its users is impossible through the API, and a forgotten tenant check in a use case no longer leaks rows: the filter stops it.
- Every request makes one more database query to load the user. It is a lookup by an indexed column.
- A user can only ever act for its own tenant. Someone who works for two companies needs two accounts.
- Anything that adds an entity with a tenant must call its field `tenantId`. `TenantFilterTest` fails the build if an entity stores `tenant_id` under another name.
- The tenant of a background job or a console command is not the signed-in user's, because there is none. Such code gets the tenant it works for as an explicit argument.

## Known limits

These are deliberate and recorded so they are not mistaken for oversights.

- **No refresh token.** After an hour the client signs in again. A refresh flow comes with the frontend (E05).
- **No sign-in throttling and no lockout.** Rate limiting is part of the security work in E10.
- **No user management API.** Users are created from the console. An admin screen comes later.
- **No second factor.**
- **A small timing difference** exists between an unknown email and a wrong password, because only the second hashes. The answers are identical; the response time is not.
- **A token is valid until it expires, or until its user is disabled.** There is no way to revoke one token alone.
