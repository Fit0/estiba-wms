<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Type;

use App\Catalog\Domain\PackagingLevel;
use App\Catalog\Domain\PackagingType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

/**
 * Stores the packaging levels of a product as a JSON list, smallest level first. A product
 * has a few levels that are always read and written with it.
 */
final class PackagingLevelsType extends Type
{
    public const string NAME = 'product_packaging_levels';

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
        foreach ($value as $level) {
            if (!$level instanceof PackagingLevel) {
                throw InvalidType::new($level, self::class, [PackagingLevel::class]);
            }

            $rows[] = ['type' => $level->type->value, 'base_units' => $level->baseUnits];
        }

        return json_encode($rows, \JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<PackagingLevel>
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

        $levels = [];
        foreach ($rows as $row) {
            if (!\is_array($row) || !\is_string($row['type'] ?? null) || !\is_int($row['base_units'] ?? null)) {
                throw ValueNotConvertible::new($value, self::class);
            }

            $levels[] = PackagingLevel::of(PackagingType::from($row['type']), $row['base_units']);
        }

        return $levels;
    }
}
