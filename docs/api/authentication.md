# Authentication

Every endpoint under `/api` needs a token, except signing in, renewing the session, signing out and the documentation. The decisions are in [ADR 0008](../adr/0008-jwt-authentication-and-tenancy-from-the-signed-in-user.md) and [ADR 0022](../adr/0022-sessions-with-a-rotating-refresh-token-in-a-cookie.md).

## Sign in

```bash
curl -s -X POST http://localhost:8080/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email": "admin@acme.test", "password": "correct-horse-battery"}'
```

```json
{ "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..." }
```

Send the token on every request:

```bash
curl -s http://localhost:8080/api/me -H 'Authorization: Bearer <token>'
```

A token lasts **one hour**. After that the API answers `shared.token_expired`, and the client renews it (below) or signs in again.

## Keep the session: refresh and sign out

Signing in also sets a cookie, `estiba_refresh`: the refresh token of the session. A script ignores it. A browser app does not have to do anything with it either, because JavaScript cannot read it (`HttpOnly`), the browser sends it only to `/api/auth/*` (`Path`) and only from our own site (`SameSite=Strict`).

```bash
curl -s -X POST http://localhost:8080/api/auth/refresh --cookie 'estiba_refresh=<secret>'
```

```json
{ "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..." }
```

The answer is a new access token and a new cookie: **each refresh token works once**. Send no body and no `Authorization` header. The session lasts 30 days from signing in at most, however often it is renewed, and a refresh token lives 7 days if it is not used.

`POST /api/auth/logout` revokes the refresh token and everything renewed from it, and clears the cookie. It answers `204` whether or not there was a session. The access token that was already issued works until it expires, up to an hour, as the user is still active.

Two tabs that renew at the same moment with the same secret are not an attack: the second one is refused for 10 seconds without ending the session, and the client asks again with the cookie the first answer set. A spent token that shows up after that revokes the whole session, since someone kept a copy.

## Who am I

`GET /api/me` returns the user, its roles and its tenant:

```json
{
  "id": "01a1596b-10a2-7a63-bbec-9cfaa84381aa",
  "email": "admin@acme.test",
  "roles": ["admin"],
  "tenant": { "id": "01a1596b-105a-7adc-9406-15b30518a01f", "name": "Acme Logistics", "defaultLanguage": "es" }
}
```

## The tenant

Every user belongs to one tenant, and every request acts for it. There is nothing to send: the tenant is the signed-in user's. A request cannot ask to act for another tenant, and what it asks about another tenant's data is answered as if it did not exist.

## Roles

Each role includes the ones below it.

| Role | Can |
|---|---|
| `operator` | Read everything of its tenant, and receive goods: open a receipt, count what arrives, close it and put the goods away |
| `supervisor` | Everything an operator can, and create and change warehouses, zones, locations, products and ASNs, and approve an over-receipt |
| `admin` | Everything a supervisor can |

The API reads the roles from the database on every request, not from the token, so a change of role or a disabled account applies at once.

## Errors

| Status | Code | When |
|---|---|---|
| 401 | `identity.credentials_invalid` | Signing in with a wrong password, an unknown email or a disabled account. The answer is the same for all three. |
| 401 | `shared.unauthenticated` | The request carries no token |
| 401 | `shared.token_invalid` | The token is malformed, forged, or belongs to a disabled user |
| 401 | `shared.token_expired` | The token is older than one hour |
| 401 | `identity.refresh_token_invalid` | Renewing a session whose cookie is missing, unknown, expired, revoked or spent, or whose user is disabled. The cookie is cleared. |
| 403 | `shared.forbidden` | The user's role does not allow this |

A 401 caused by a token carries `WWW-Authenticate: Bearer`. All of them use the [common error shape](errors.md) and the language of `Accept-Language`.

## Setting up a tenant

There is no registration endpoint. A tenant and its first administrator are created from the console, and the administrator's password is asked for with the typing hidden (or read from `ESTIBA_PASSWORD` in scripts), never taken as an argument:

```bash
make tenant name="Acme Logistics" email=admin@acme.test
```

More users:

```bash
docker compose exec php bin/console app:user:create <tenant-id> sup@acme.test --role=supervisor
docker compose exec php bin/console app:user:disable sup@acme.test
docker compose exec php bin/console app:user:enable sup@acme.test
```

A password has 12 to 72 characters.

## Keys

Tokens are signed with RS256. `make jwt-keys` creates the key pair in `backend/config/jwt/`, which git ignores, and the development container does it on first start. `JWT_PASSPHRASE` is random per machine: `make up` writes it to `backend/.env`, which git ignores. In production the key files and `JWT_PASSPHRASE` come from the environment. To rotate them in development, delete `backend/.env` and `backend/config/jwt/*.pem` and run `make up` again; existing tokens stop working.
