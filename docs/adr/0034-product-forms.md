# 0034. Product forms

- Status: Accepted
- Date: 2026-10-29

## Context

The catalogue could be read but not written from the screen: products existed only through the API. E06-01b adds the forms to register a product, change it, and complete its data (barcodes, packaging, classification), for supervisors and admins.

## Decisions

- **Same stack as the warehouse forms** (ADR 0029): React Hook Form, a Zod schema per form with message keys, a `FormDialog`, and the sentence of the API shown inside the form when it refuses (SKU taken, GTIN with a bad check digit, a discontinued product).
- **The schemas repeat the cheap rules of the domain** (SKU and family format, lengths, whole numbers of 1 or more, expiry needs lot control, shelf life only with expiry control) so most mistakes are told before sending. The API keeps the last word; nothing is trusted from the screen.
- **A merge patch for the edition.** The product page edits name and measures and sends only what changed, with `Content-Type: application/merge-patch+json`; if nothing changed, nothing is sent. The SKU is shown but cannot change.
- **One action per fact.** GTIN, packaging level and classification are their own dialogs, because the API has one command for each; each answers with the whole product, which replaces the cached one, and the lists are asked again.
- **Dialogs live only while open.** The page mounts a form when it is asked for and unmounts it on close, so it always starts from the product as it is now and without an old error.
- **Who and when.** Only supervisor and admin see the buttons, and not for a discontinued product (the API refuses changes to it as well).

## Consequences

- A rule that changes in the domain must change in the schema too; if it is forgotten, the user sees the API sentence instead of the field message, which is worse but still correct.
- Dimensions are in millimetres and weight in grams, as the API takes them; no unit conversion on the screen.
