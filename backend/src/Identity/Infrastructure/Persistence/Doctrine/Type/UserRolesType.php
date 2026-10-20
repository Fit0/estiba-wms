<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Type;

use App\Identity\Domain\Role;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

/**
 * Stores the roles of a user as a JSON list in the user row. A user has one to three roles that
 * are always read and written with it.
 */
final class UserRolesType extends Type
{
    public const string NAME = 'user_roles';

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

        $names = [];
        foreach ($value as $role) {
            if (!$role instanceof Role) {
                throw InvalidType::new($role, self::class, [Role::class]);
            }

            $names[] = $role->value;
        }

        return json_encode($names, \JSON_THROW_ON_ERROR);
    }

    /**
     * @return non-empty-list<Role>
     */
    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): array
    {
        if (!\is_string($value)) {
            throw ValueNotConvertible::new(get_debug_type($value), self::class);
        }

        $names = json_decode($value, true, 4, \JSON_THROW_ON_ERROR);
        if (!\is_array($names) || [] === $names) {
            throw ValueNotConvertible::new($value, self::class);
        }

        $roles = [];
        foreach ($names as $name) {
            $role = \is_string($name) ? Role::tryFrom($name) : null;
            if (!$role instanceof Role) {
                throw ValueNotConvertible::new($value, self::class);
            }

            $roles[] = $role;
        }

        return $roles;
    }
}
