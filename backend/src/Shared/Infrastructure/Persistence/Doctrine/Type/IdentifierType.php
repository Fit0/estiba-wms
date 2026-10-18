<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Domain\Identifier;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\GuidType;

/**
 * Stores an {@see Identifier} in a native UUID column. Each identifier class gets a
 * small subclass that says how to rebuild it.
 */
abstract class IdentifierType extends GuidType
{
    abstract protected function fromDatabase(string $value): Identifier;

    #[\Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof Identifier) {
            throw InvalidType::new($value, static::class, ['null', Identifier::class]);
        }

        return $value->value;
    }

    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Identifier
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw ValueNotConvertible::new(get_debug_type($value), static::class);
        }

        return $this->fromDatabase($value);
    }
}
