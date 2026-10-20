<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Type;

use App\Identity\Domain\PasswordHash;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\StringType;

/**
 * Stores a {@see PasswordHash}. It is not Stringable on purpose, so a hash cannot end up in a log
 * by accident, and this type is one of the few places that reads its value.
 */
final class PasswordHashType extends StringType
{
    public const string NAME = 'password_hash';

    #[\Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof PasswordHash) {
            throw InvalidType::new($value, self::class, ['null', PasswordHash::class]);
        }

        return $value->value;
    }

    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?PasswordHash
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value) || '' === $value) {
            throw ValueNotConvertible::new(get_debug_type($value), self::class);
        }

        return new PasswordHash($value);
    }
}
