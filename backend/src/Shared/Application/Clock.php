<?php

declare(strict_types=1);

namespace App\Shared\Application;

/**
 * The current time. The domain never reads the clock, so use cases read it here and hand it in.
 */
interface Clock
{
    public function now(): \DateTimeImmutable;
}
