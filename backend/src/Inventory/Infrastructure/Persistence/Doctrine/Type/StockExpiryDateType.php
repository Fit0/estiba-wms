<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

/**
 * The expiry date of a stock item, as part of its unique key (DD-1). No expiry date is stored as
 * 9999-12-31 rather than NULL, for the same reason as {@see StockLotType}: a unique index treats
 * every NULL as different (ADR 0011). No real product expires on that day.
 */
final class StockExpiryDateType extends Type
{
    public const string NAME = 'stock_expiry_date';

    private const string FORMAT = 'Y-m-d';

    private const string NEVER = '9999-12-31';

    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getDateTypeDeclarationSQL($column);
    }

    #[\Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string
    {
        if (null === $value) {
            return self::NEVER;
        }

        if (!$value instanceof \DateTimeImmutable) {
            throw InvalidType::new($value, self::class, ['null', \DateTimeImmutable::class]);
        }

        return $value->format(self::FORMAT);
    }

    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\DateTimeImmutable
    {
        if (!\is_string($value)) {
            throw ValueNotConvertible::new(get_debug_type($value), self::class);
        }

        if (self::NEVER === $value) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!'.self::FORMAT, $value);

        return false === $date ? throw ValueNotConvertible::new($value, self::class) : $date;
    }
}
