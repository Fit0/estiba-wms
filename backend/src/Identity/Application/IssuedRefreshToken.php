<?php

declare(strict_types=1);

namespace App\Identity\Application;

/**
 * A refresh token as the client gets it: the secret, which is shown once, and when it stops working.
 */
final readonly class IssuedRefreshToken
{
    public function __construct(
        #[\SensitiveParameter]
        public string $secret,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
