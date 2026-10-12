# Catalog

What the warehouse handles: products, SKUs, GTIN barcodes, units of measure and physical dimensions.

| Layer | Holds |
|---|---|
| `Domain` | `Product`, `Sku`, `Gtin` (check-digit validated), `UnitOfMeasure`, `Dimensions` |
| `Application` | Use cases to register and update products. `Application/Contract` is the only part other contexts may depend on. |
| `Infrastructure` | Doctrine XML mapping and repositories, API resources, controllers |

The aggregates, events and published contracts are defined during domain modelling (E01-06).
