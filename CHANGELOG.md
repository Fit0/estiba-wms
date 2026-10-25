# Changelog

Notable changes by version. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Decisions are in `docs/adr/`.

## [0.1.0] - 2026-10-25

The backend can run a warehouse from the delivery of a supplier to the dispatch of an order to a carrier, through an API with its own documentation. There is no interface yet.

### Added

- **Foundations**: Docker environment, quality tooling (PHPStan at max level with architecture rules, PHP-CS-Fixer, Rector), CI with five jobs, one translated problem shape for every error, Spanish and English.
- **Identity**: tenants and users, JWT sign-in, roles `admin`, `supervisor` and `operator`, tenant isolation by use case and by SQL filter.
- **Warehouse and Catalog**: warehouses, zones, locations with a pick sequence, products with GTINs, packaging levels, tracking by lot and expiry date.
- **Inventory**: stock as an immutable ledger, reservations with FEFO and FIFO allocation, a stock read model.
- **Inbound**: advance shipping notices (ASN), receipts at the dock with discrepancies and over-receipt approval, putaway with suggested locations.
- **Outbound**: orders and allocation, waves and pick lists in a serpentine route, picking with scans and short picks, packing into parcels with GS1 SSCC, labels in ZPL and PDF with a GS1-128 barcode, dispatch to a carrier.
- **Audit trail**: every domain event is written down with who caused it, and read with filters (`GET /api/audit-log`).
- **API documentation**: an OpenAPI document with a summary, the role, an example and the errors of every operation, kept true by tests.
- An end-to-end test of the whole story: a delivery arrives, is put away, sold, picked, packed and dispatched, and the stock, the ledger and the audit trail tell the same story.

### Known limits

- The labels have not been tried on a Zebra printer or with a barcode scanner. The GS1 company prefix of the SSCC is a placeholder for demos (`GS1_COMPANY_PREFIX`).
- A short pick gives the units up but does not correct the shelf: a count should. There is no cycle count yet.
- No returns, no cancellation of a parcel once closed, no undoing of a dispatch.
- The audit trail has no retention policy.
- The ERP integration is not built: the confirmation of a dispatch to the ERP waits for it.
