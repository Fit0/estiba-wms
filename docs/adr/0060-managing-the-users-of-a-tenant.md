# 0060. Managing the users of a tenant

- Status: Accepted
- Date: 2026-11-16

## Context

Users could only be created and disabled from the console. An admin of a tenant had no way to add a colleague, change what someone may do, or lock an account without a shell on the server. The users screen (E06-08) needs an API for it.

Two mistakes are easy to make from a screen and expensive to undo: an admin who disables or demotes themselves, and a tenant that ends up with no active admin, which nobody can fix from inside the application.

## Options considered

1. **Only checks in the screen.** Cheap, but the API stays open to the same mistake and the rule lives in the wrong place.
2. **Checks in the handlers.** The rule is enforced, but the aggregate can still be told to do it by any other caller, such as the console.
3. **The aggregate enforces the rule and the handler enforces what depends on who is asking.** "A tenant keeps one active admin" is a fact about users, so `User` decides it, given a small port that counts the active admins. "You cannot change your own access" depends on the signed-in user, so the handler decides it.

## Decision

Option 3.

- `User::disable()` and the new `User::changeRoles()` receive an `ActiveAdmins` port (`countIn(tenant)`) and refuse to leave the tenant without an active admin (IDN-11). `UserRepository` implements it with one SQL count.
- The handlers of the API refuse to let a user disable itself or remove its own admin role (IDN-12). Adding roles to oneself is allowed.
- Changing roles replaces the whole set and records `UserRolesChanged`, which the audit trail writes like any other event. Setting the same roles records nothing.
- The admin types the initial password, which goes through `PlainPassword` and is never shown or stored in clear. There is no reset of another user's password in this version.
- The endpoints live under `/api/users` and need the `admin` role in `security.yaml`. The tenant comes from `CurrentTenant`, and a user of another tenant is a 404.

## Consequences

- The rule that protects the tenant holds for the API and the console alike.
- Because the actor must be another active admin, IDN-11 cannot be reached through the API and is covered by unit tests only.
- A forgotten password needs an admin to disable the account and create a new one, until a reset flow exists.
