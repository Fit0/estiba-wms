<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Domain\ProductFamily;
use App\Shared\Domain\StorageClass;
use App\Warehouse\Domain\DisplayName;
use App\Warehouse\Domain\PickSequence;
use App\Warehouse\Domain\Zone;
use App\Warehouse\Domain\ZoneCode;
use App\Warehouse\Domain\ZoneId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

/**
 * Stores the zones of a warehouse as a JSON document in the warehouse row. A warehouse has
 * a handful of zones that are always read and written with it, and Doctrine can only fill
 * a relation with its own collection class, which the domain must not import.
 */
final class ZonesType extends Type
{
    public const string NAME = 'warehouse_zones';

    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    #[\Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string
    {
        if (!\is_array($value)) {
            throw InvalidType::new($value, self::class, ['array']);
        }

        $rows = [];
        foreach ($value as $zone) {
            if (!$zone instanceof Zone) {
                throw InvalidType::new($zone, self::class, [Zone::class]);
            }

            $rows[] = [
                'id' => $zone->id->value,
                'code' => $zone->code->value,
                'name' => $zone->name->value,
                'storage_class' => $zone->storageClass->value,
                'pick_sequence' => $zone->pickSequence->value,
                'families' => array_map(static fn (ProductFamily $family): string => $family->value, $zone->families),
            ];
        }

        return json_encode($rows, \JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<Zone>
     */
    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): array
    {
        if (!\is_string($value)) {
            throw ValueNotConvertible::new(get_debug_type($value), self::class);
        }

        $rows = json_decode($value, true, 8, \JSON_THROW_ON_ERROR);
        if (!\is_array($rows)) {
            throw ValueNotConvertible::new($value, self::class);
        }

        $zones = [];
        foreach ($rows as $row) {
            $zones[] = $this->zoneFrom($row, $value);
        }

        return $zones;
    }

    private function zoneFrom(mixed $row, string $json): Zone
    {
        if (!\is_array($row)
            || !\is_string($row['id'] ?? null)
            || !\is_string($row['code'] ?? null)
            || !\is_string($row['name'] ?? null)
            || !\is_string($row['storage_class'] ?? null)
            || !\is_int($row['pick_sequence'] ?? null)
        ) {
            throw ValueNotConvertible::new($json, self::class);
        }

        return new Zone(
            ZoneId::fromString($row['id']),
            ZoneCode::fromString($row['code']),
            DisplayName::fromString($row['name']),
            StorageClass::from($row['storage_class']),
            PickSequence::of($row['pick_sequence']),
            $this->familiesFrom($row['families'] ?? [], $json),
        );
    }

    /**
     * Zones saved before families existed have none.
     *
     * @return list<ProductFamily>
     */
    private function familiesFrom(mixed $families, string $json): array
    {
        if (!\is_array($families)) {
            throw ValueNotConvertible::new($json, self::class);
        }

        $read = [];
        foreach ($families as $family) {
            if (!\is_string($family)) {
                throw ValueNotConvertible::new($json, self::class);
            }

            $read[] = ProductFamily::fromString($family);
        }

        return $read;
    }
}
