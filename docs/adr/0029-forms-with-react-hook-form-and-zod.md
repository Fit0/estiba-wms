# 0029. Forms with React Hook Form and Zod, and the active warehouse

- Status: Accepted
- Date: 2026-10-28

## Context

The screens of the back office start with warehouses, zones and locations (E05-07), and every later screen (products, orders, receipts) has forms. A form has to check what is typed before it asks the API, show each error next to its field in the language that is on, and show what the API says when it refuses. Two more questions came up: what the API lets a screen do, and how the screens know which warehouse they work on.

## Decisions

- **React Hook Form with a Zod schema** (`@hookform/resolvers/zod`). The fields are uncontrolled, so typing does not draw the whole form again, and the schema is the one place that says what a value may be. The schemas live in `src/warehouse/schemas.ts` and repeat the rules of the domain (`WarehouseCode`, `ZoneCode`, `LocationCode`, `DisplayName`, capacity limits ≥ 1). The API repeats them too and has the last word: what it refuses is shown under the form, in the sentence it wrote in the language asked for.
- **The messages of a schema are keys of the catalog**, and the form turns them into text with `useFieldError()`. A wrong key does not compile (`message()` takes a `MessageKey`), and `i18n.test.ts` keeps both languages complete.
- **Inputs are strings, the output is typed.** A form holds text; the schema turns `'20'` into `20`, an empty limit into `null` and a code into capitals. `useForm<Input, unknown, Output>` keeps the two apart, so what reaches the API is already the right type.
- **Zod also reads what OpenAPI cannot say.** The document types `Warehouse.zones` as a list of anything. `zoneSchema` reads it at run time, so a change in the API is an error and not a blank table.
- **One dialog for every form** (`FormDialog`): title, fields, the refusal of the API in a `role="alert"` and Cancel / Save, with the button disabled while the request is on its way. Closing it empties the form and the error.
- **What the API allows is what the screen offers.** There is no edit or delete of a warehouse or a location: the stock ledger refers to them, so a location leaves service by being **blocked** (with a reason) and comes back by being unblocked. The screens create warehouses, zones and locations, and block and unblock locations. Renaming or retiring a warehouse is a decision for the domain, not for the screen.
- **Roles.** Everyone signed in reads; supervisors and admins see the buttons that change things. The API is what refuses (`security.yaml`).
- **The active warehouse** is chosen in the top bar (`WarehouseSelect`, a Radix select) and kept in `localStorage` (`estiba-warehouse`) with a fallback when the browser refuses it. `ActiveWarehouseProvider` sits in the layout, reads the list through the cache, and falls back to the first warehouse if the chosen one is gone. The screens that depend on a warehouse (receiving, picking) read `useActiveWarehouse()`.

## Consequences

- A new form is a schema, a `useForm` and a `FormDialog`; the tests of E05-07 show the pattern.
- Locations are listed a page at a time with `X-Total-Count` (ADR 0026), filtered by status on the server.
- The unblock action has no dialog because it asks for nothing; a failed unblock is shown by the cache as an unchanged row (a notice for failures comes with the error handling of E06).
