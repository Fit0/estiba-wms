# Warehouse

Where goods can be stored: warehouses, zones and locations.

| Layer | Holds |
|---|---|
| `Domain` | `Warehouse`, `Zone`, `Location`, `LocationCode` (`aisle-module-level`), location capacity and status |
| `Application` | Use cases to lay out a warehouse and block or unblock locations. `Application/Contract` is the only part other contexts may depend on. |
| `Infrastructure` | Doctrine XML mapping and repositories, API resources, controllers |

The aggregates, events and published contracts are defined during domain modelling (E01-06).
