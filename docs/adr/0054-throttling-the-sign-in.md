# 0054. Throttling the sign-in

- Status: Accepted
- Date: 2026-11-15

## Context

[ADR 0053](0053-security-headers-non-root-images-and-dependency-updates.md) left one hole open: `POST /api/auth/login` answers as fast as a client asks, so a password can be guessed by trying them all. The passwords are at least 12 characters (IDN-03), which makes that slow, not impossible, and the answer is the same for a wrong password and an unknown email, so a client cannot even tell whether it is guessing a real account. Nothing stopped it from trying.

The decision is how to count the tries, where to keep the count, and what the client is told when it is over the limit.

## Options considered

**A counter of our own in Redis.** It is a few lines, and it is one more piece of security code to write, test and get wrong: the window, the reset after a good sign-in, the key. Symfony Security already has this.

**The `login_throttling` of the firewall**, which is made for this. It needs `symfony/rate-limiter`, which was not in the table of dependencies. It counts the tries of an email from an address, and the tries of an address, and forgets the first when the sign-in works.

**A limit in the web server.** The Caddy of the frontend image has no rate limiting in its standard build, and a plugin would mean building our own Caddy. It would also limit by address only, which punishes everybody behind one office router and does not slow an attack on one account from many addresses.

## Decision

**`login_throttling` on the `login` firewall, with `symfony/rate-limiter`**, approved by the author. In fifteen minutes, five tries for the same email from the same address, and twenty-five from one address for any email. The next try is refused, **even with the right password**, until the window moves on. A sign-in that works starts the count of that email again.

**The state is in Redis**, in a pool of its own (`cache.login_throttling`), and not in the files of the container: every instance counts the same tries, and a restart does not forget them. In tests the state is an in-memory storage service (`InMemoryStorage`) and not a pool: the tests do not reboot the kernel between requests, and Symfony empties a pool in memory each time it resets the services, so a pool would forget the tries at every request. The storage is not reset, and each test boots its own kernel, so the tries of one test are never counted against another.

**The answer is a 429.** It is a problem of the usual shape, with the code `identity.too_many_login_attempts`, a translated text, and a `Retry-After` header in seconds. The seconds come from the limiter and are rounded up to the minute. The text does not name a number: the header does.

**An email that does not exist is counted like one that does**, so the 429 does not tell an attacker which accounts exist, which is what the 401 already took care of.

**Locking is off.** The lock needs `symfony/lock`, which is another dependency. Without it two tries at the same instant can both get in, which is a few tries over the limit and not a way round it.

**The address is the real one.** Behind a proxy that ends TLS, the address of every request is the proxy's unless `SYMFONY_TRUSTED_PROXIES` names it (ADR 0053). Otherwise all clients share one limit of twenty-five tries and one of them can lock out the rest. The documentation says so.

## Consequences

- A person who forgets the password five times waits up to fifteen minutes. An attacker who guesses an email from another address does not lock that person out, since the count is by email and address together.
- The count of an email uses the email as it arrives, with case folded. Spaces around it make another count, so a client could get more than five tries for one account. The limit by address, twenty-five, is what holds that, and it is why it exists.
- A client with many addresses is not slowed. Nothing here is a defence against a spread attack, and the checklist says so.
- **If Redis is down, signing in fails with a 500**, because the count cannot be read. It is the safe way to fail, and `/health` already reports Redis as down.
- Renewing a session (`POST /api/auth/refresh`) is not limited: its secret is 32 random bytes and cannot be guessed. The other operations of the API are not limited either, and the checklist keeps that as open.
- One dependency more, `symfony/rate-limiter`, and a line in the table of `CLAUDE.md`.
