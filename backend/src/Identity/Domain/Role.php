<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Identity\Domain\Exception\InvalidRole;

/**
 * What a user may do. Each role includes the ones below it: an admin can do what a supervisor
 * can, and a supervisor what an operator can (ADR 0008).
 */
enum Role: string
{
    /** Manages the tenant and its users. */
    case Admin = 'admin';

    /** Plans and changes the warehouse, the catalog and the orders. */
    case Supervisor = 'supervisor';

    /** Works on the floor: reads, and confirms tasks. */
    case Operator = 'operator';

    /**
     * @throws InvalidRole
     */
    public static function fromString(string $value): self
    {
        return self::tryFrom(strtolower(trim($value))) ?? throw InvalidRole::forValue($value);
    }
}
