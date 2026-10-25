<?php

declare(strict_types=1);

namespace App\Identity\Application\RotateRefreshToken;

use App\Shared\Application\Bus\Command;

/**
 * Exchanges the secret of a refresh token for a new one. The answer is `null` when it cannot be
 * exchanged. That is not an exception: when a spent token shows up again the family is revoked, and
 * that change must be committed, not rolled back.
 *
 * @implements Command<RotatedRefreshToken|null>
 */
final readonly class RotateRefreshToken implements Command
{
    public function __construct(
        #[\SensitiveParameter]
        public string $secret,
    ) {
    }
}
