# 0036. Waves screens

- Status: Accepted
- Date: 2026-10-29

## Context

Second part of E06-04 (ADR 0035 has the orders). A wave groups reserved orders and, once released, becomes pick lists for the floor. Supervisors plan and release; everyone follows the state.

## Decisions

- **Three views.** `/waves` lists them (status filter, paging, ADR 0031). `/waves/:id` shows the orders in it and the pick lists with `picked of to pick` units per list. The order page links to its wave.
- **Waves have no reference**, so they are named by the start of their id (8 characters) until the API gives them one.
- **Making a wave means choosing orders.** The dialog offers the orders that are fully reserved, or partly reserved with the backorder accepted, in no wave yet and of the active warehouse (two requests of up to 100, one per status). The API checks the same and that all orders share a warehouse, and its sentence is shown if it refuses.
- **Release asks for the number of pick lists** (1 to 20), one per operator. The OpenAPI document types that optional body as an empty object, so the call casts it once, in `api/waves.ts`, and nowhere else.
- **Cancelling asks first** and shows the refusal of the API in the dialog (for example once a pick has been confirmed).
- **Live state by polling** every 15 seconds, as in the orders (ADR 0035). A change on a wave refreshes the lists of waves, its pick lists and all the orders, because their status moves with it.
- **Out of scope here:** assigning pick lists to operators needs a list of operators that the API does not have yet, and confirming picks is the handheld screen of E07.

## Consequences

- A list of more than 100 ready orders will not show them all; the dialog needs a search or paging if that happens.
- Pick lists show the operator as the start of an id until there is an endpoint to read operator names.
