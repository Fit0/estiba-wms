# 0022. Sessions: a rotating refresh token in an HttpOnly cookie

- Status: Accepted
- Date: 2026-10-25

## Context

[ADR 0008](0008-jwt-authentication-and-tenancy-from-the-signed-in-user.md) gave the API one-hour access tokens and left a known limit: no refresh token, so after an hour the client signs in again. A warehouse shift is eight hours, and a picker with a scanner in one hand cannot type a password every hour. The React app (E05-03) needs a way to keep the session alive, and where it keeps its secrets matters, because an app that stores a long-lived token where JavaScript can read it hands that token to any script injected into the page (XSS).

## Options considered

1. **A longer access token.** Simple, and a stolen token works for as long. Nothing can end it.
2. **Renew a token that is still valid** (`POST /api/auth/refresh` with the old access token). Little code, but the token has to be kept in `localStorage` or `sessionStorage` to survive a reload, and a stolen one can be renewed until the cap.
3. **A refresh token in the response body**, kept by the app in `localStorage`. The usual tutorial, and readable by XSS.
4. **An opaque refresh token in an `HttpOnly` cookie**, rotated at every use. The access token lives only in the memory of the page. JavaScript never sees the secret that lasts.

## Decision

Option 4.

- **Signing in** answers as before (`{ "token": "..." }`) and sets the cookie `estiba_refresh`: `HttpOnly`, `SameSite=Strict`, `Path=/api/auth`, `Secure` when the request is HTTPS, expiring with the token.
- **`POST /api/auth/refresh`** takes no body. It exchanges the cookie for a new access token and a new cookie.
- **`POST /api/auth/logout`** revokes the session and clears the cookie.
- **The secret** is 256 random bits. Only its SHA-256 is stored (a fast hash is right here: the secret is random, not a password), in `refresh_tokens`, so a leaked table cannot be used. It carries no claims: whether it is good is always asked of the database.
- **Rotation and reuse.** Each token works once and gives a successor of the same family (`IDN-08`). A spent token that shows up again revokes the whole family (`IDN-09`), because someone has a copy. There are 10 seconds of grace in which a spent token is only refused: two tabs that start together send the same cookie, and signing the user out for that would be wrong. The client asks again, and by then the cookie holds the new secret.
- **Limits of time.** A token lives 7 days; a session, 30 days from signing in, however often it is renewed (`IDN-10`). All three numbers are parameters in `services.yaml`.
- **A disabled user cannot renew** (`IDN-06`): the user is loaded from the database at every refresh, like at every request.
- **The routes need no token.** They are in the `login` firewall and are public in `access_control`. An expired access token that the client sends along does not break them.
- **No CSRF token.** `SameSite=Strict` stops the browser from sending the cookie from another site, the cookie goes only to routes whose answer a foreign page cannot read (there is no CORS), and the worst a forged call can do is renew or end a session.
- **Failures are one answer.** Missing, unknown, expired, revoked, spent or of a disabled user: `401 identity.refresh_token_invalid`, with the cookie cleared. It does not tell which.
- **Not an exception.** Refusing a spent token must revoke the family, and the command bus rolls back when a handler throws, so the handler returns `null` and the controller answers.

## Consequences

- The app keeps nothing it can lose to XSS that lasts: the access token is in memory for an hour, and the cookie is out of reach. A script in the page can still use the session while the page is open, which no storage choice prevents.
- A reload loses the access token and the app gets another with one call to `/refresh`, so it needs a "restoring the session" state before it knows who is signed in.
- The browser must send the cookie, so the app and the API share an origin (the dev proxy of ADR 0021, a reverse proxy in production). A client on another origin would need CORS with credentials, and a decision of its own.
- `refresh_tokens` grows with every renewal. Nothing removes old rows yet: a job that deletes the ones whose session ended is a follow-up, and until then the table is small (one row per renewal, per user).
- An access token cannot be revoked and works until it expires, up to an hour, after signing out. Disabling the user does cut it at once (ADR 0008).
- Signing in and renewing are not audited yet. The audit trail has no event for them.
- The routes are not rate limited. Login has the same gap.
