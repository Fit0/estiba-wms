<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\LotNumber;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\StringType;

/**
 * The lot of a stock item, as part of its unique key (DD-1). No lot is stored as an empty text
 * rather than NULL, because a unique index treats every NULL as different and would let two stock
 * items share a key (ADR 0011). A lot number is never empty, so the two cannot be confused.
 */
final class StockLotType extends StringType
{
    public const string NAME = 'stock_lot';

    private const string NO_LOT = '';

    #[\Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string
    {
        if (null === $value) {
            return self::NO_LOT;
        }

        if (!$value instanceof LotNumber) {
            throw InvalidType::new($value, self::class, ['null', LotNumber::class]);
        }

        return $value->value;
    }

    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?LotNumber
    {
        if (!\is_string($value)) {
            throw ValueNotConvertible::new(get_debug_type($value), self::class);
        }

        return self::NO_LOT === $value ? null : LotNumber::fromString($value);
    }
}
