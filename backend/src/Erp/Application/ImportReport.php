<?php

declare(strict_types=1);

namespace App\Erp\Application;

/**
 * What one run of the import did, counted in orders and files.
 */
final readonly class ImportReport
{
    public function __construct(
        public int $registered = 0,
        public int $alreadyRegistered = 0,
        public int $rejectedFiles = 0,
    ) {
    }
}
