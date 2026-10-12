# 0002. Doctrine XML mapping outside the domain

- Status: Accepted
- Date: 2026-10-12

## Context

Doctrine ORM is the persistence tool. Its mapping can live in attributes on the entity, in XML or YAML files, or in a separate persistence model.

ADR 0001 requires the domain layer to be free of framework code. Doctrine attributes (`#[ORM\Entity]`, `#[ORM\Column]`) are framework code.

## Options considered

1. **Attributes on domain entities.** Least code and best IDE support, but the domain imports Doctrine and the architecture rule cannot hold.
2. **XML mapping files in `Infrastructure`.** The domain classes stay plain PHP. Doctrine reads the mapping from a separate file.
3. **Separate persistence models plus mappers.** Complete isolation, but every aggregate is written twice and a mapper must translate between the two forms.

## Decision

Option 2. Each context keeps its Doctrine mapping as XML in `Infrastructure/Persistence/Doctrine/mapping/` and registers it in `config/packages/doctrine.yaml`:

```yaml
doctrine:
    orm:
        mappings:
            Inventory:
                type: xml
                is_bundle: false
                dir: '%kernel.project_dir%/src/Inventory/Infrastructure/Persistence/Doctrine/mapping'
                prefix: 'App\Inventory\Domain'
```

Value objects are mapped as embeddables or with custom DBAL types. `validate_xml_mapping` is already enabled, so Doctrine checks every mapping file against its schema.

Repository interfaces live in `Domain`. Their Doctrine implementations live in `Infrastructure`.

## Consequences

- The domain has no Doctrine import, and `ArchitectureTest` fails the build if one appears.
- XML is more verbose than attributes, and renaming a property means editing two files.
- There is no `src/Entity` folder. The default mapping from the Symfony recipe was removed.
- Migrations are generated from the mapping with `doctrine:migrations:diff` and reviewed by hand.
