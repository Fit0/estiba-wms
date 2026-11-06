# 0046. A simulated legacy ERP

- Status: Accepted
- Date: 2026-11-06

## Context

Estiba is meant to live next to an ERP that owns the customer orders, as the warehouse systems it is modelled on do. The integration is the part of the project that most resembles real work: an old system with its own words, formats and habits, and an anti-corruption layer that keeps them out of the domain (E08-02). To build and test that layer there must be an ERP to talk to, and it must be old in the ways that matter: orders leave it as files, confirmations go back through SOAP, and its answers are codes.

The plan asks for "a container with an old PHP application that exposes SOAP and leaves CSV files on SFTP". The questions are how old, how it is made, and how it is run.

## Options considered

1. **A current PHP application with SOAP and SFTP.** Easy to build and to test here. It would not look like a legacy system, so the anti-corruption layer would have little to translate, and the story the project tells would be weaker.
2. **PHP 5.6 with the SOAP extension.** The closest to a real old ERP. The `php:5.6` image is built on a Debian whose package mirrors are gone, so installing `ext-soap` (it needs `libxml2-dev`) means rewriting the image's apt sources, and a build that breaks for reasons outside the repository.
3. **PHP 5.6 with a hand-written SOAP endpoint.** Needs nothing the base image lacks: it reads the request with DOM and writes the answer as text. Hand-made SOAP was common in that time, before and beside the libraries. The cost is that the endpoint is code we own and must test.

For the data: **MySQL** is what such an ERP would use, but needs a database server and the `mysql` extension in the old image; **SQLite** through PDO needs neither and is in the base image. For the SFTP server: a **shared folder** is simpler but the WMS adapter would never speak SFTP; a **separate SFTP container** is what E08-03 needs.

## Decision

The ERP lives in `erp-legacy/`, outside `backend/`, and is built from `php:5.6-apache` (option 3).

- **Procedural code, Spanish names, SQLite.** Global functions and `include`, no classes or namespaces, `PDO` with SQLite for the data. The names of fields, operations and codes are Spanish, so the anti-corruption layer has a real vocabulary to translate.
- **Orders go out as files.** Every few seconds a loop in the container writes one Latin-1, semicolon-separated CSV file per new order. A file is written under a hidden name and renamed, so a reader never sees half of one.
- **Shipments come back as SOAP.** A static WSDL and two operations, `ConfirmarEnvio` and `ConsultarPedido`. Business problems are codes in the answer, never faults. A second confirmation of the same order answers code 1 and changes nothing, which is the case an outbox that retries will meet (E08-04).
- **The SOAP reader refuses `DOCTYPE`** and loads with `LIBXML_NONET`, so a message cannot make the ERP read a file or call a host.
- **A separate `erp-sftp` container** (`atmoz/sftp`) shows the output folder of the ERP through a volume they share. The folder is open to every user (`0777`) because the two containers run their processes with different user ids. This is only for development.
- **Behind a Compose profile.** `make erp-up` starts the ERP; `make up` does not. The usual start stays fast, and an old image that fails to build cannot stop anybody from working on the rest. `make down` stops both.
- **A check that needs no tools.** `bin/comprobar.php` takes an order, reads its file, asks about it and confirms it over SOAP. `make erp-check` runs it in the container, and it also runs with any PHP against the built-in server.

The order files and the SOAP service are described in [erp-legacy/README.md](../../erp-legacy/README.md). It is the contract: the WMS adapters are written against it.

## Consequences

- E08-02 and E08-03 have a real counterpart to develop against, and the ERP's quirks (dates as `dd/mm/yyyy`, a single-letter urgency, articles by SKU, Latin-1) are the material for the anti-corruption layer and for the post about it.
- The SOAP endpoint is ours, so it can differ from a real server in details: it follows the WSDL and `SoapClient` accepts it, but a real ERP might reject a message this one allows. The contract tests of E08-05 must use the WSDL, not this endpoint's leniency.
- The articles of the ERP are copied from the demo catalogue of the WMS (`inc/catalogo.php`). They have to be changed together.
- The image cannot be tested in the CI of this repository without Docker-in-Docker, and PHP 5.6 is not available outside the image. What was checked by hand is the behaviour under a current PHP, and that the code parses as PHP 5.6. The first `make erp-up` is the real test of the image.
- Nothing in `backend/` may depend on `erp-legacy/`. The WMS talks to the ERP over the network only, through the adapters of E08-02 and E08-03.
