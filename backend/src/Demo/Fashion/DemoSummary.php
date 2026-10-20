<?php

declare(strict_types=1);

namespace App\Demo\Fashion;

/**
 * What the demo loader created, for the console to show.
 */
final readonly class DemoSummary
{
    /**
     * @param array<string, string> $users the role of each user, by email address
     */
    public function __construct(
        public string $tenantId,
        public string $tenantName,
        public array $users,
        public string $warehouseCode,
        public int $zones,
        public int $locations,
        public int $products,
    ) {
    }
}
