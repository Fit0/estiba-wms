# 0032. The receiving screens

- Status: Accepted
- Date: 2026-10-29

## Context

E06-02 puts the inbound flow of the backend (ASN, receipt, count, discrepancies, over-receipt, close) in front of the operator at the dock. The API decides everything that matters: which product a barcode is, what is a shortage or an excess, when a receipt may close. The screens have to let a person count fast and see what the API found, without a second copy of those rules.

## Decisions

- **Three screens, one path.** `/inbound` lists the expected deliveries (status, page and size in the address, like ADR 0031); `/inbound/:id` shows the lines of one delivery and the way into its receipt; `/receipts/:id` is where the counting happens. Opening a receipt goes straight to it.
- **The dock comes from the active warehouse** (ADR 0029). The dialog lists its active locations of type dock. The API cannot filter locations by type, so `useDockLocations` reads them a page of 100 at a time and keeps the docks; if there is none, the dialog says to create one.
- **The product is a barcode.** The form asks for the GTIN, the units, the lot, the expiry date and the condition (good or damaged). A scanner that acts as a keyboard fills the first field; after each count the form clears and the cursor returns to it. The camera scanner is E07-02. The form checks the shape (8–14 digits, a whole number of units, a date) and leaves the digit check, the product and the lot rules to the API, whose message is shown under the form.
- **The screen computes nothing the API already says.** Discrepancies, the excess and the approval come from the receipt; the screen only groups the counts by product to show expected, counted and difference, and turns each answer into the receipt in the cache (`setQueryData`), so the page updates without a second request.
- **Names of products** come from the products already in the cache, one query per distinct id (`useProductsById`), because the API has no call for several at once. While they load, a short id is shown.
- **Closing asks first** (`ConfirmDialog`), and the button is disabled while there is an excess nobody approved; the API would refuse anyway (INB-08). A supervisor approves the excess on the same screen.
- **Operators do the work of the dock; supervisors approve.** The screens show a button only to the role that can use it, and the API refuses the rest.

## Consequences

- A delivery with many products makes many small requests for names; if it ever hurts, the fix is a `ids` filter on `GET /api/products`, not a cache on the client.
- A warehouse with more than 5 000 locations would make the dock dialog read 50 pages; a `type` filter on the locations endpoint would end that.
- Registering an ASN from the screen (supervisor) is not part of E06-02.
