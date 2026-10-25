# 0019. OpenAPI document completed from one table and checked by tests

- Status: Accepted
- Date: 2026-10-25

## Context

API Platform writes the shape of every request and response from the resources, and that is most of the work. What it cannot know is what a person needs to use the API: what an operation does and which rules decide the answer, who may call it, what a good request looks like, which filters a list takes and which errors the API really gives. The document of version 0.1 said "Creates a Wave resource" for cancelling one, had no example, no security scheme, and listed errors (`Error`, `ConstraintViolation`) that are not the ones the API answers. Several things had to be decided:

- **Where the documentation is written.**
- **How it is applied** to the document.
- **How it is kept true** as the API grows.

## Options considered

1. **An `openapi` option on each operation** of each resource (27 files). The text sits next to the operation, but it is spread out, hard to review as a whole, and pulls documentation prose into the resource classes.
2. **Documentation in the PHPDoc of the classes.** API Platform reads some of it, but it covers the shape and not examples, roles or filters.
3. **One table, `config/api/operations.yaml`**, applied by a decorator of the OpenAPI normalizer.
4. **A generated document committed to the repository** (`openapi.json`). Easy to diff, and one more file that drifts from the code.
5. **Tests that compare** the table with the real operations and the real firewall.

## Decision

- **One table** (option 3): for each operation a summary, the rules, the role, an example of the body and the filters of the list, written once and reviewed as a whole. The resources stay free of documentation prose.
- **A decorator of the normalizer** (`DocumentedOpenApiNormalizer`) lets API Platform write the document and then hands the array to `OpenApiDocumentation`, a function that adds what the table says, and no more. Working on the array and not on the immutable model objects of the library keeps it short and lets it be tested without the framework.
- **What it adds besides the table.** The Bearer security scheme, with no token for the login. The `Accept-Language` header on every operation. A `Problem` schema, the shape of ADR 0007, and six error responses with an example each, that replace the errors API Platform guessed: each operation gets the ones that can happen to it (400 for a body, a filter or an id, 401 always, 403 above operator, 404 for an id, 409 and 422 for a change). Paging parameters for the lists that are paged. The tags, with a description. The label of a parcel, which a controller answers and API Platform does not know.
- **Tests keep it true** (option 5), and no generated file is committed:
  - every operation of the API is in the table and every entry is an operation (a new endpoint without documentation fails the build);
  - every operation has its own summary, a tag and the language header;
  - every body has an example, whose fields are fields of the schema and that has the required ones;
  - the errors are the ones of the API;
  - **the role of each operation is the one the firewall asks for**: an operator is refused exactly what says supervisor, and a supervisor is refused nothing.
- The document is published at `/api/docs` for people and at `/api/docs.jsonopenapi` for tools.

## Consequences

- Adding an endpoint now means adding its entry to the table. The test says so, and the message names what is missing.
- The errors of an operation are decided by rule (by its role and its path) and not one by one. They say what *can* happen: an operation that never answers 409 can say so with `conflicts: false`.
- The examples are written by hand and checked against the schema, but they are not run against the API. An example that passes the shape and would be refused by a rule (a unique code already used) would not be caught. The ids in them are made up.
- The responses of the operations are still what API Platform writes. They do not have examples. They are the resources, whose fields are named for what they are; examples of answers are for later.
- The label operation lives only in the table, since API Platform does not know it. It is checked like the others.
