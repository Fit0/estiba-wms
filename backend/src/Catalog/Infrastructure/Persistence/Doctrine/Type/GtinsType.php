<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Type;

use App\Catalog\Domain\Gtin;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

/**
 * Stores the GTINs of a product as a JSON list of numbers. The uniqueness per tenant (CAT-02)
 * is kept apart, in the `product_gtins` table that the repository maintains.
 */
final class GtinsType extends Type
{
    public const string NAME = 'product_gtins';

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

        $numbers = [];
        foreach ($value as $gtin) {
            if (!$gtin instanceof Gtin) {
                throw InvalidType::new($gtin, self::class, [Gtin::class]);
            }

            $numbers[] = $gtin->value;
        }

        return json_encode($numbers, \JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<Gtin>
     */
    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): array
    {
        if (!\is_string($value)) {
            throw ValueNotConvertible::new(get_debug_type($value), self::class);
        }

        $numbers = json_decode($value, true, 4, \JSON_THROW_ON_ERROR);
        if (!\is_array($numbers)) {
            throw ValueNotConvertible::new($value, self::class);
        }

        $gtins = [];
        foreach ($numbers as $number) {
            if (!\is_string($number)) {
                throw ValueNotConvertible::new($value, self::class);
            }

            $gtins[] = Gtin::fromString($number);
        }

        return $gtins;
    }
}
