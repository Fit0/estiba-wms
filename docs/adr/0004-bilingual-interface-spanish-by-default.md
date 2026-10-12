# 0004. Bilingual interface, Spanish by default

- Status: Accepted
- Date: 2026-10-12

## Context

Estiba is meant to be used on a warehouse floor in Spain, so its natural language is Spanish. It is also a public project that people outside Spain will read and try, and an English interface lets them follow a demo.

The code, the commits and the documentation are in English (CLAUDE.md). The [ubiquitous language](../domain/ubiquitous-language.md) already pairs each English term with the Spanish term staff use.

Adding a second language late means touching every screen and every error message. Doing it from the start costs little, but it changes how errors are raised, so it must be decided before the first domain exception exists.

## Options considered

1. **Spanish only.** Least work, but a reviewer who does not read Spanish cannot follow a demo.
2. **English only.** Simple, but it does not match the people who would use a warehouse system in Spain.
3. **Spanish and English, with Spanish by default.** Every user-visible text goes through a translation catalog, and errors carry codes instead of sentences.
4. **Translations stored in the database.** Allows editing without a deploy, but adds an admin screen and a cache, and nothing here needs it.

## Decision

Option 3.

- **Languages:** Spanish (`es`, the default) and English (`en`).
- **Domain code has no human text.** Every domain exception implements a `DomainError` interface in `Shared\Domain` that exposes a stable `code` (for example `inventory.stock_negative`) and its parameters. The code is the contract; the sentence is not. The interface arrives with the first domain exception in E02.
- **Backend:** the Symfony Translation component with ICU message format, and catalogs in `backend/translations/` (`messages.es.yaml`, `messages.en.yaml`). The language comes from the `Accept-Language` header, falling back to Spanish. API errors follow RFC 9457 problem details: `type`, `code`, a translated `detail`, and `violations` that each carry their own `code`.
- **Frontend:** `react-i18next` with one catalog per language (`es.json`, `en.json`). The language is the user's saved choice, then the browser language, then Spanish. Text is never glued from fragments, plurals use ICU, and dates and numbers go through `Intl` with the active language.
- **Parity is tested.** A test fails the build when a code or a key exists in one language and not in the other.
- **Data entered by users** (product descriptions, supplier names) is stored as typed and is not translated.
- **Background jobs and printed labels** have no request, so they use the tenant's default language, set in the tenant settings.
- **Terminology:** UI translations follow the ubiquitous language. The Spanish column is the Spanish term, and the English column is the English term.

## Consequences

- Domain tests assert error codes, not sentences, so changing a translation never breaks a domain test.
- Every new error and every new screen text needs two translations. The parity test makes forgetting one impossible to merge.
- A new language is a new catalog file in each of backend and frontend, with no code change.
- `react-i18next` and the Symfony Translation component are new dependencies, recorded in CLAUDE.md.
- Two small tasks are added to the roadmap: error codes and translation in the backend (E02), and i18n in the frontend (E05).
