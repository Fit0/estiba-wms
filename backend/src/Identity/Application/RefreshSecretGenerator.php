<?php

declare(strict_types=1);

namespace App\Identity\Application;

/**
 * Creates the secret of a refresh token: long, random and unguessable. The domain never generates it.
 */
interface RefreshSecretGenerator
{
    /**
     * @return non-empty-string
     */
    public function next(): string;
}
