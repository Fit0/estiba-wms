<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Persistence\Doctrine\Type;

use App\Warehouse\Domain\PickSequence;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

final class PickSequenceType extends Type
{
    public const string NAME = 'pick_sequence';

    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getIntegerTypeDeclarationSQL($column);
    }

    #[\Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?int
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof PickSequence) {
            throw InvalidType::new($value, self::class, ['null', PickSequence::class]);
        }

        return $value->value;
    }

    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?PickSequence
    {
        if (null === $value) {
            return null;
        }

        if (!\is_int($value)) {
            throw ValueNotConvertible::new(get_debug_type($value), self::class);
        }

        return PickSequence::of($value);
    }
}
