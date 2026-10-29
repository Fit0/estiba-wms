# 0035. Orders screens

- Status: Accepted
- Date: 2026-10-29

## Context

E06-04 asks for orders and waves on screen. It is the biggest task of the epic, so it is delivered in two parts: first the orders (this ADR), then the waves and their live state.

## Decisions

- **List, registration and one order.** `/orders` lists the orders as the API sorts them (most urgent first), filtered by status on the server and paged (ADR 0031). `/orders/:id` shows the data, the address and the lines with ordered, allocated, short, picked and shipped units. Every signed-in user reads; the buttons are for supervisors and admins.
- **Registration is a dialog for the active warehouse** (ADR 0029). The form has the reference, customer, priority, cut-off time, the address and a list of lines (`useFieldArray`). Each line picks its product by typing a part of the SKU or name, with the same control as the stock filter (ADR 0033), so the id is never typed by hand. The address goes to the API nested as `shipTo`, as the contract says.
- **The actions follow the state of the order.** Reserve stock (registered or partly allocated), accept the backorder (partly allocated and not yet accepted) and cancel (until it is picked). These rules only decide which buttons to show; the API refuses what is not allowed and its sentence is shown next to the button or in the dialog.
- **Cancelling asks first**, since it releases the reservations.
- **Polling, not push.** The list and the order ask again every 15 seconds while open (`refetchInterval`), so what the floor does shows without a reload. TanStack Query pauses it in a hidden tab. Waves use the same mechanism (next part).

## Consequences

- The product control is shared with the stock screen, so a change in how products are found changes both.
- A cut-off time is written in the local time of the browser and sent as ISO 8601 with its offset.
- With polling a screen can lag by up to 15 seconds; push (Mercure) was left out because it needs server and infrastructure work that this task does not justify.
