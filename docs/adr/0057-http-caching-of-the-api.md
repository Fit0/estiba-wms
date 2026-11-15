# 0057. HTTP caching of the API

- Status: Accepted
- Date: 2026-11-15

## Context

E10-06 asked for HTTP caching. There are two kinds of answer. The frontend server already serves the files of the build with a hash in their name for a year (`immutable`) and compressed (ADR 0052). The API answers JSON that belongs to one signed-in user, changes with every task a person confirms, and is read by screens that ask again every 15 or 30 seconds.

## Decision

**The API does not ask any cache to keep an answer.** Symfony sends `Cache-Control: no-cache, private` when a response says nothing, and that is what the lists, the records and the errors carry: private to the user, and checked again each time. A test (`ApiCacheControlTest`) fails if a list stops being private or an answer gets a `max-age`, so a later change cannot make a shared cache hold the data of a tenant.

**No ETag and no conditional requests.** With an ETag the browser would get a `304` and no body, which saves bytes, not work: the server still runs the query and builds the answer to compare it. The answers are small (a page of 30) and the load test shows that the cost of a request is in the server, not in the transfer. It would also add a rule to every provider for no gain that a measurement shows.

**The count of a list is exact.** `X-Total-Count` is part of the contract (ADR 0026). It costs a pass over the table, and over the whole ledger it is about 80 ms at 500 000 movements (ADR 0056).

## Consequences

- A second tab or a second user does not share an answer: each asks the API.
- If the API is ever put behind a shared cache, the lists stay out of it by their `private`, and only what a person decides to publish (the documentation, the files of the build) can be kept.
