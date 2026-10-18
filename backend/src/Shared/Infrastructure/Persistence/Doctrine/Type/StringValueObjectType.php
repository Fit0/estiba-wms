<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\StringType;

/**
 * Stores a value object that is a validated string. Each value object gets a small
 * subclass that says how to rebuild it. Values read from the database go through the
 * same validation as new ones.
 */
abstract class StringValueObjectType extends StringType
{
    abstract protected function fromDatabase(string $value): \Stringable;

    #[\Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof \Stringable) {
            throw InvalidType::new($value, static::class, ['null', \Stringable::class]);
        }

        return (string) $value;
    }

    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?\Stringable
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
