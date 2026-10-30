# 0038. Forms for expected deliveries

- Status: Accepted
- Date: 2026-10-30

## Context

The receiving screens (ADR 0032) could read and receive a delivery, but not register, cancel or close one: those calls existed in the API only. E06-02b closes the gap, as E06-01b did for products.

## Decisions

- **Same stack as the other forms** (ADR 0029 and 0034): React Hook Form, a Zod schema with message keys, a `FormDialog`, and the sentence of the API shown inside the form when it refuses (for example a reference already in use).
- **The delivery form has the lines of the order form** (ADR 0035): a `useFieldArray`, with each product chosen by typing a part of its SKU or name. Lot and expiry date are optional on every line: the API decides whether the product needs them, and its answer is shown if it does and they are missing. The expected date is written in local time and sent as ISO 8601.
- **Cancel and close ask first**, from the delivery page, for supervisors and admins. The buttons follow the state: cancel only while the delivery is still expected, close only once goods have arrived, and neither while a receipt is open. The API checks the same.
- **Shared code.** The default date of a `datetime-local` field moved to `components/dateTimeInput.ts`, used by both forms.

## Consequences

- A delivery for a product that needs lot or expiry can be rejected by the API after the form is sent; a hint from the product (`trackedByLot`) would avoid it but means one request per line, so it was left out.
- Editing the lines of a registered delivery is not offered, since the API has no call for it.
