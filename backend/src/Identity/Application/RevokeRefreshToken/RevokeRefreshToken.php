<?php

declare(strict_types=1);

namespace App\Identity\Application\RevokeRefreshToken;

use App\Shared\Application\Bus\Command;

/**
 * Ends the session a refresh token belongs to. A secret that is not known is ignored, so signing
 * out never fails.
 *
 * @implements Command<null>
 */
final readonly class RevokeRefreshToken implements Command
{
    public function __construct(
        #[\SensitiveParameter]
        public string $secret,
    ) {
    }
}
