# 0007. One translated problem shape for every API error

- Status: Accepted
- Date: 2026-10-19

## Context

[ADR 0004](0004-bilingual-interface-spanish-by-default.md) decided that errors carry a stable code and that the API translates them. [ADR 0006](0006-http-api-with-api-platform-and-dto-resources.md) built the HTTP API and turned broken domain rules into `application/problem+json`, with an English `detail`. It left every other error to API Platform and Symfony.

That left the API answering errors in three different shapes: a domain rule in the project's shape, a body the serializer cannot read in API Platform's shape, and a route that does not exist in Symfony's plain JSON. In development the last two also carried a stack trace, and none of them was translated. A client had to handle each shape, and the English text of two of them reached users.

## Options considered

1. **Leave the other errors as they are.** No work, but the three shapes stay, and so does the English text.
2. **Convert every error in one listener.** One place maps each kind of exception to a code and a status, and one factory builds the response.
3. **Replace API Platform's error handling with its own error resource and normalizer.** It is the framework's intended hook, but it ties the project to internals that change between major versions, and it does not cover errors Symfony raises before API Platform runs, such as an unknown route.

## Decision

Option 2. `ApiErrorListener` handles every error under `/api`, and `ProblemFactory` builds the response.

- **One shape.** Every error is `application/problem+json` with `type` (`urn:estiba:error:<code>`), `title`, `status`, `code`, `detail` and `parameters`.
- **Codes.** A broken domain rule keeps its own code and parameters. Everything else gets a generic `shared.*` code, listed in the `HttpProblem` enum: `tenant_missing` (401), `request_malformed` (400, for a body that is not JSON, a missing field or a wrong type), `not_found` (404), `method_not_allowed` (405), `not_acceptable` (406), `unsupported_media_type` (415), `request_invalid` (any other client error) and `internal_error` (500).
- **Language.** It comes from `Accept-Language`, with Spanish as the default and English as the only other option. A regional variant such as `en-GB` uses its language, and a language the API does not speak gets Spanish. The response says which one it used in `Content-Language` and adds `Vary: Accept-Language`.
- **What is translated.** `title` and `detail`. `type`, `code`, `status` and `parameters` never change with the language.
- **Texts.** They live in `backend/translations/errors+intl-icu.es.yaml` and `.en.yaml`, keyed by code, in ICU format. A parameter that picks the wording, such as a field name, is an ICU `select`.
- **Unexpected errors.** Outside debug they become `shared.internal_error`, and the real exception is logged, so no internal detail leaves the API. In `dev` and `test` they are left alone so the stack trace stays visible.
- **Contract tests.** `ErrorCatalogTest` reads every code from the source and fails the build when a code has no text in a language, when a text is left for a code that no longer exists, when an ICU pattern is invalid, or when a text uses a parameter the error does not carry.

This replaces the paragraph of ADR 0006 that left the errors found by the serializer as API Platform's own 400.

## Consequences

- A client switches on `code`, and shows `detail` as it is or writes its own text.
- Adding a code means adding its text in both languages. The build refuses anything else.
- The API reports one error per request. A body with several wrong fields is a single `shared.request_malformed`, and the response does not say which field. Field-level `violations`, as ADR 0004 sketched, wait until the forms in the frontend need them (E05 and E06).
- Authentication will add its own codes (401 and 403) with E02-06, and they go through the same factory.
- If a code ever reaches production without a translation, the response falls back to the English message of the exception instead of showing a bare code.
