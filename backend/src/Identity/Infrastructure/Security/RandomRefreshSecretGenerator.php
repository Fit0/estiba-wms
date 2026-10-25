<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\RefreshSecretGenerator;

/**
 * 256 bits from the system's random source, as hexadecimal text, which is safe in a cookie.
 */
final readonly class RandomRefreshSecretGenerator implements RefreshSecretGenerator
{
    #[\Override]
    public function next(): string
    {
        return bin2hex(random_bytes(32));
    }
}
