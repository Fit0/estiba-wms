<?php

declare(strict_types=1);

namespace App\Identity\Domain;

/**
 * The result of hashing a password. The domain does not know how it was made or how to check
 * it: that belongs to the {@see PasswordHasher} and to the security layer.
 */
final readonly class PasswordHash
{
    /**
     * @param non-empty-string $value
     */
    public function __construct(#[\SensitiveParameter] public string $value)
    {
    }

    public function __debugInfo(): array
    {
        return [];
    }
}
