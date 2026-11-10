<?php

declare(strict_types=1);

namespace App\Erp\Application;

/**
 * What one run of the outbox did, counted in confirmations.
 */
final readonly class SendReport
{
    public function __construct(
        public int $sent = 0,
        public int $toRetry = 0,
        public int $failed = 0,
    ) {
    }
}
