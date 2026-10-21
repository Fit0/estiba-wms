<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\Quantity;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

/**
 * Stores a {@see Quantity} as a whole number of base units. Reading goes through `Quantity::of()`,
 * so a negative number in the database fails loudly (STK-01).
 */
final class QuantityType extends Type
{
    public const string NAME = 'stock_quantity';

    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getIntegerTypeDeclarationSQL($column);
    }

    #[\Override]
    public function getBindingType(): ParameterType
    {
        return ParameterType::INTEGER;
    }

    #[\Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?int
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof Quantity) {
            throw InvalidType::new($value, self::class, ['null', Quantity::class]);
        }

        return $value->units;
    }

    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Quantity
    {
        if (null === $value) {
            return null;
        }

        if (\is_string($value) && is_numeric($value)) {
            $value = (int) $value;
        }

        if (!\is_int($value)) {
            throw ValueNotConvertible::new(get_debug_type($value), self::class);
        }

        return Quantity::of($value);
    }
}
