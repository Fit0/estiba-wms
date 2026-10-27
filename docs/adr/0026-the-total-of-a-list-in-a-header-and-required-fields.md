# 0026. The total of a list in a header, and required fields in the OpenAPI document

- Status: Accepted
- Date: 2026-10-28

## Context

The typed client of ADR 0025 showed two gaps in what the API says about itself:

1. **A list has no total.** The state providers build a paginator that knows the total, but the format is plain JSON, so the answer is a bare array. A screen with a paging bar ("Showing 26–50 of 1,240", the buttons of the pages) cannot be drawn from it.
2. **No field is required.** API Platform lists the properties of a resource and marks none as required, so in the generated TypeScript every field is optional and each screen has to cope with an `id` that may be missing.

## Options considered

For the total:

1. **Hydra (`application/ld+json`).** It carries `hydra:totalItems` and the links to the other pages. It is the way API Platform does it, but it changes the shape of every list (an object with `member`), the format of every client and the examples of ADR 0019, to add one number.
2. **An envelope of our own** (`{ "items": [...], "total": 125 }`). The same disruption, in a shape nobody else uses.
3. **A header, `X-Total-Count`**, on the answer of every paged list. The body stays an array, nothing that works changes, and the number is one line for a client to read.
4. **Guess from the page**: a full page means there may be another. No total, no page buttons, and an extra request that comes back empty.

For the required fields:

5. **Annotate each property** with `#[ApiProperty(required: true)]` in the 20 resources. Explicit, and 150 repetitions of what the type already says.
6. **Derive it in the decorator** that already completes the document (ADR 0019): a field is required unless its type allows `null`.

## Decision

- **A header** (option 3). `TotalCountListener` reads the paginator that the state provider built, which is the total of the query with its filters and not the size of the page, and adds `X-Total-Count` to a 200 answer. The document says it for every paged list (`headers` of the 200 response), so the generated types have it. One stock line, a list that is not paged and any error have no header.
- **Required by the type** (option 6). `OpenApiDocumentation` marks as required every property of a resource schema whose type does not include `null`. This is true because the API leaves out what is null and sends everything else (the JSON is built with the null values skipped, see the stock tests), and the resources are plain classes whose types say which fields can be null. The schemas of request bodies (`...Input`, `...jsonMergePatch`) and the error shapes are left alone: what a body must have is validation, not the shape of an answer. A functional test checks, for stock, that every field the document requires is in a real answer, and the unit tests cover the rule.
- **The client reads both.** `unwrapPage` returns `{ items, total }`, and `Schema<...>` is now just the generated type, without the `Required<>` that ADR 0025 used to hide the gap.

## Consequences

- A table can draw its paging bar: `useStockItems` gives `data.total`, and `DataTable` takes it as `pagination.total`.
- A new field that is not nullable and sometimes missing is a bug the document now makes visible, instead of one every screen absorbed.
- A field typed as nullable in PHP but always sent is documented as possibly missing. That is the safe side.
- The header is not on a response that is cached by a client that ignores it. The `Vary` and cache headers of the API already keep one copy per user (`Authorization`) and language.
- If a screen needs the links to the next and the previous page, or a cursor instead of a page number, this ADR is the place to revisit; for tables with a page selector, the total is all that is missing.
