<?php

declare(strict_types=1);

namespace App\Identity\Domain;

/**
 * Lets {@see User::register()} check that an email address is free (IDN-01) without knowing how
 * users are stored. A unique database constraint backs the check against races.
 */
interface EmailRegistry
{
    public function isTaken(Email $email): bool;
}
