# 0006. HTTP API with API Platform and DTO resources

- Status: Accepted. The paragraph on errors the serializer finds is superseded by [ADR 0007](0007-one-translated-problem-shape-for-every-api-error.md).
- Date: 2026-10-18

## Context

E02-04 exposes Warehouse and Catalog over HTTP. API Platform is the chosen framework, and it can build an API straight from Doctrine entities. ADR 0002 and the dependency rule keep the domain free of framework code, so entities cannot carry API attributes, and a resource that mirrors an entity would tie the public contract to the persistence model.

Four questions had to be settled: what the resources are, who runs the use case, how a broken domain rule becomes a response, and where the tenant comes from before authentication exists (E02-06).

## Options considered

1. **Resources.** API attributes on domain entities (breaks ADR 0001), API Platform's Doctrine integration over a second set of entities (two models), or plain DTO classes in `Infrastructure` filled by our own state providers and processors.
2. **Use cases.** Processors call the domain directly, or they call an application handler per use case.
3. **Errors.** API Platform's `exception_to_status` table keyed by class, or a listener that reads the stable error code.
4. **Tenant.** A fixed development tenant, a header, or a stub JWT.

## Decision

1. Resources are DTO classes in `<Context>/Infrastructure/Http/Resource`, with inputs in `Http/Input` and the state providers and processors in `Http/State`. The Doctrine integration of API Platform is switched off (`api_platform.doctrine.enabled: false`). Output only carries what a client needs, flattened (identifiers as strings, enums as their values).
2. A processor builds a command, calls its handler in `Application/<UseCase>/` and flushes the entity manager, which is what the command bus will do from E03-02. Handlers hold the use case, so replacing the processor with a bus does not touch them. Reads go through query handlers or a `...Lookup` service. A lookup reports another tenant's aggregate as not found, so identifiers of other tenants are not revealed.
3. `DomainErrorListener` turns any `DomainError` into `application/problem+json` with `code`, `parameters` and an English `detail`. The status comes from the code: `*_not_found` is 404, `*_already_*`, `*_not_blocked` and `*_discontinued` are 409, a malformed identifier is 400 and everything else is 422. Error translation arrives in E02-05 and only changes `detail`. Errors the serializer finds (a missing field, an unknown enum value) stay API Platform's own 400.
4. `HeaderCurrentTenant` reads `X-Tenant-Id` and a missing or malformed value is a 401, never "all tenants". It sits behind the `CurrentTenant` interface, so E02-06 replaces only this class with the tenant of the JWT.

The API speaks plain JSON (`application/json`, and `application/merge-patch+json` for `PATCH`). JSON-LD and Hydra are off because no client needs them and they triple the size of every response. Actions that are not a plain create or update are `POST /<resource>/{id}/<verb>` (`block`, `unblock`, `discontinue`).

## Consequences

- The public contract can change without a migration, and the domain model can change without breaking clients.
- Every operation needs a resource, an input, a processor and a handler. It is more files than the Doctrine integration would need, and it is what keeps the layers apart.
- `X-Tenant-Id` is trusted as given until E02-06, so the API must not be exposed before authentication exists.
- A list returns a bare JSON array. Paging is `page` and `itemsPerPage` (at most 100); the total is not in the body yet.
