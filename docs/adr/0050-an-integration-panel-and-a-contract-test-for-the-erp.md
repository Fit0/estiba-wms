# 0050. An integration panel and a contract test for the ERP

- Status: Accepted
- Date: 2026-11-10

## Context

The integration with the ERP runs on its own, every 30 seconds (ADR 0048, ADR 0049). When it works nobody looks at it; when it does not, the only places to look were an SFTP folder, a table and the logs. A supervisor needs to see what went out and what came in, and to put a failed confirmation back in the queue (ADR 0049 left that to this change). And the contract with the ERP lives in two copies: the WSDL of the simulated ERP (`erp-legacy/inc/erp.wsdl`) and the one the tests of the gateway use (`backend/tests/Support/erp.wsdl`). If the ERP changes and the copy does not, the tests pass against a contract that no longer exists.

## Options considered

For the history of imported files: **reading the SFTP folders** (`processed/` and `rejected/`) each time the panel is opened, which is slow, ties the screen to the ERP's availability and says nothing about why a file was rejected; **the application log**, which is not queryable; or **a table in the database**, written by the importer.

For the drift of the WSDL: **one file read by both** (the backend container only mounts `backend/`, so it cannot see `erp-legacy/`), **a script in CI** that compares them, or **a unit test**, which runs wherever the tests run and fails with the rest.

## Decision

- **`erp_import_log` records every file the import dealt with**: its name, the result (`processed` with the number of orders, or `rejected` with the error code and message) and when. The importer writes the row just before it moves the file, so the log and the folders agree. The table is read and written with SQL, with a mapping for the schema, as the other two tables of the context.
- **Three endpoints under `/api/erp`**, for supervisors and above (`security.yaml`): `GET /confirmations` (filter `status`), `GET /imports` (filter `result`), both paged with the latest first, and `POST /confirmations/{shipmentId}/retry`. The retry only works on a `failed` confirmation: it goes back to `pending` with the attempts at zero, the error cleared and due now, so the next run of the job sends it. Anything else answers `erp.failed_confirmation_not_found` (404), including a confirmation of another tenant. The retry does not call the ERP itself: the job is the only sender, so a person and the schedule can never send the same confirmation at once.
- **The panel** (`/erp`, "Integración ERP") shows both lists with their filters and pages in the address, as the other lists do (ADR 0031), asks again every 10 seconds, and has a retry button on each failed row.
- **`WsdlContractTest` compares the two WSDL files**, ignoring line endings and the `location` of the service (the ERP fills it in; the copy points to a fixed test address). If the original is not reachable, as when the tests run in a container that mounts only `backend/`, the test is skipped and says why; CI checks out the whole repository, so it runs there.

## Consequences

- A rejected file can be explained from the screen, and a failed confirmation can be retried without SQL.
- The retry is not immediate: it is sent at the next run, at most 30 seconds later.
- The log grows with every file and has no retention yet. One row per file is small; a purge by age is simple if it ever matters.
- Files imported before this change are not in the log, only in the folders.
- Changing the WSDL of the ERP means changing the copy of the tests in the same commit, which is the point of the test.
