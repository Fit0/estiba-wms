<?php

declare(strict_types=1);

namespace App\Identity\Application\RotateRefreshToken;

use App\Identity\Application\IssuedRefreshToken;

/**
 * The new refresh token and who it is for, so that an access token can be made for them.
 */
final readonly class RotatedRefreshToken
{
    public function __construct(
        public string $email,
        public IssuedRefreshToken $refreshToken,
    ) {
    }
}
