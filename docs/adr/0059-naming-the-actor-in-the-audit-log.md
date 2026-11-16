# 0059. Naming the actor in the audit log

- Status: Accepted
- Date: 2026-11-16

## Context

An audit entry keeps the id of the user who caused the event (AUD-02), and the screen showed it as a string of hexadecimal digits that nobody can read. The person who opens the log is a supervisor asking who did something, and needs a name.

The audit trail does not write personal data (ADR 0018): the email of a user is `#[Sensitive]` in the events and never reaches an entry. Users are Identity's, and Audit knows no context (ADR 0018).

## Options considered

1. **Write the email into the entry when the event is recorded.** It reads well forever, but it puts personal data in a table that only grows and cannot be corrected (AUD-01), and it keeps the old address after the user changes it.
2. **The frontend asks for the users.** There is no endpoint to read users, and one for supervisors would show the whole directory to answer a question about ten rows.
3. **Audit asks Identity for the emails of the users on the page when it lists the trail.** One query per page, through a contract, and nothing is stored.

## Decision

Option 3. Identity publishes `UserDirectory::emailsOf(tenant, ids)`, answered with plain SQL over `users` and restricted to the tenant. The handler that lists the trail asks it once for the distinct actors of the page and returns each entry with `actorEmail`, which is `null` when the user cannot be found or the event had no user (a policy that ran in a worker). `Audit` may now depend on `Identity`, and only through that contract: the architecture test says so.

## Consequences

- The trail stays free of personal data and the name follows the user: a corrected address shows up in old entries.
- Audit is no longer a context that depends on nothing. The dependency goes one way, and Identity still depends on nothing.
- The list does one more query per page. It is a primary-key lookup of at most 100 ids.
- Filtering by the email of the actor is not possible, since the trail only holds the id. The filter by user id stays; a screen that wants to filter by person picks the user first (E06-08).
