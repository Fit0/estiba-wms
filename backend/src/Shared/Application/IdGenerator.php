<?php

declare(strict_types=1);

namespace App\Shared\Application;

/**
 * Creates identifiers for new aggregates (UUID v7). The domain never generates them.
 */
interface IdGenerator
{
    /**
     * @return non-empty-string
     */
    public function next(): string;
}
