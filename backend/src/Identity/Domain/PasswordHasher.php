<?php

declare(strict_types=1);

namespace App\Identity\Domain;

/**
 * Turns a password into a hash that is safe to store. The adapter uses Symfony's hasher, so the
 * algorithm can change without touching the domain.
 */
interface PasswordHasher
{
    public function hash(PlainPassword $password): PasswordHash;
}
