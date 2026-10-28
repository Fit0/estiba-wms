# 0028. react-i18next and a button to change language

- Status: Accepted
- Date: 2026-10-28

## Context

The frontend had a small catalog with the shape of `t` (ADR 0022 left it open to be replaced). The plan asks for Spanish by default, a language choice that is remembered, catalogs in both languages, dates and numbers written the local way, and a check in CI that no key is missing in either language.

## Options considered

1. **Keep the small catalog.** Nothing to add, but plurals, interpolation and a language that changes while the screen is open are all ours to write.
2. **react-i18next.** The common library, with interpolation and re-rendering when the language changes.
3. **A dropdown with the languages.** Room for more languages. There are two, so it is a click more for no gain.

## Decision

- **Option 2**, in its own instance (`i18next.createInstance()`), so tests and the app cannot share state by accident. The catalogs stay typed TypeScript files (`es.ts`, `en.ts`) and `MessageKey` comes from the Spanish one, so a wrong key does not compile. The wrapper `useTranslation()` returns `{ language, t }` with that type.
- **Spanish is the default**; the stored choice wins, then the browser's preference, then Spanish.
- **One button in the top bar and on the sign-in page** switches between the two. It shows the code of the language it will switch to, and its accessible name says it in full, in the language that is on. The command palette has the same action.
- **The choice is saved in `localStorage`** (`estiba-language`) with a fallback when the browser refuses it, and `<html lang>` follows every change.
- **The API is told**: every request carries `Accept-Language` with the current language, so the messages of the backend (Symfony Translation) come back in it.
- **Numbers and dates** go through `Intl` in `i18n/format.ts` (`formatNumber`, `formatDate`, `formatDateTime`). A plain date (`YYYY-MM-DD`) is written as UTC so it does not move a day.
- **CI**: `i18n.test.ts` fails if a key is in one catalog and not in the other, if a text is empty, or if the placeholders differ.

## Consequences

- A third language is a new catalog and an entry in `LANGUAGES`, plus the button turning into a menu.
- The tests start every case in Spanish (`test-setup.ts`).
