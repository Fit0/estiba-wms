# 0017. Packing parcels with SSCC labels

- Status: Accepted
- Date: 2026-10-24

## Context

Once the picked units wait at staging ([ADR 0016](0016-confirming-picks-and-moving-the-stock-to-staging.md)) somebody puts them into boxes, and each box needs a code that the carrier and the customer can scan. Several things had to be decided:

- **What a parcel is in the model**, and when it gets its SSCC.
- **Where the serial of the SSCC comes from**, since two packers can close parcels at once and a code must never repeat.
- **How the label is produced**: the plan asks for PDF and ZPL, and the stack has no PDF or barcode library.
- **Who may pack**, and how the shipment relates to the order.

## Options considered

1. **A parcel opened and filled line by line**, then closed. It mirrors the work, but the system would hold half-filled boxes that nobody can verify, and each unit would need a scan.
2. **A parcel created closed**, with its contents and its weight. The packer fills a real box and records it when it is sealed. One call, and the SSCC is assigned at that moment.
3. **The serial from a PostgreSQL sequence.** It is atomic, but the schema tool of the tests does not create sequences, and a sequence never gives a number back.
4. **The serial from a counter row per company prefix**, moved with one `INSERT ... ON CONFLICT DO UPDATE ... RETURNING`. It is atomic, the row lock makes a second packer wait, and it runs inside the transaction that closes the parcel.
5. **A PDF library** such as TCPDF or Dompdf: less code of ours, a heavy dependency, and still no GS1-128 barcode out of the box in some of them.
6. **A PDF written by hand** and a Code 128 encoder of our own: more code and tests, no dependency.

## Decision

- **A parcel exists only closed** (option 2). `Shipment` holds what the order had picked when packing began, and every `closeParcel` takes a part of it: the contents by product, the weight in grams and the SSCC. The invariants are OUT-19 to OUT-24. A shipment is packed when every picked unit is in a parcel. Units that were short picked are not part of it.
- **One shipment per order** (OUT-20), backed by a unique index on the order, since two packers could start at once. Only an order in the picked status with something picked can start packing. The order does not change: the shipment is its own aggregate, and dispatch (E04-06) will connect them.
- **The SSCC is built from a counter** (option 4). `Sscc` is a value object: 18 digits with the GS1 check digit, composed of the extension digit (0), the GS1 company prefix and a serial padded to fill 17 digits. The check digit algorithm is shared with the GTIN, so it moved to `Shared` as `Gs1CheckDigit`. The prefix comes from `GS1_COMPANY_PREFIX` (7 digits by default). **The default is a placeholder for demos and tests**: a company that ships real parcels must set the prefix GS1 licensed to it. A parcel that is refused rolls back its serial with the transaction, so there are no gaps made by errors.
- **The label is a query** that answers the content (`ParcelLabel`), and two renderers in Infrastructure draw it. It can be printed again at any time. The words come from the translation catalog (`labels`), in the language of `Accept-Language`.
- **No library** (option 6). The barcode is a GS1-128: Code 128 in set C, opened by FNC1, with the application identifier 00 and the 18 digits, which fill ten symbols exactly. The encoder holds the table of 107 patterns; a test checks its width, its parity and that reading the bars back gives the same symbols. The PDF is one page of 4 by 6 inches written by hand, with the standard Helvetica fonts and a rectangle per bar. The ZPL uses `^BC` with `>;` and `>8`.
- **Operators pack.** Starting packing, closing a parcel and completing it are dock and floor work, like picking, so an operator may do them. Reading is open to anyone signed in. The label is a `GET`, so it follows the same rule.
- **The label is not a JSON resource**, so a controller answers it, at `/api/shipments/{id}/parcels/{sscc}/label.pdf` or `.zpl`. It still asks through the query bus and its errors go through the same problem shape.

## Consequences

- Each parcel closed makes one write on the shipment (the parcels are a JSON column, like the lines of an order) and one on the counter row. The counter row serializes the closing of parcels across the company, which is fine for a packing station and would need a counter per station or per block of serials if it ever were not.
- The label has not been verified on a printer or with a scanner: the checks are those of the structure (the table, the symbols read back, the cross-reference of the PDF). It must be tried on a real Zebra printer, and the barcode with a GS1 verifier, before it is trusted.
- A parcel cannot be corrected once closed: there is no reopening or voiding. A label that was printed is out in the world, so a mistake needs a rule of its own, which is for later.
- The weight is typed by the packer or read from a scale; it is not computed from the catalog.
- The shipment is not connected to the stock: dispatch (E04-06) consumes the reservation and moves the order on.
