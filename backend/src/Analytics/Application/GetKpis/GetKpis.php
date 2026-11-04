<?php

declare(strict_types=1);

namespace App\Analytics\Application\GetKpis;

use App\Shared\Application\Bus\Query;

/**
 * The operating figures of the tenant for a period. Without a period it is the last seven days.
 *
 * @implements Query<KpiReport>
 */
final readonly class GetKpis implements Query
{
    public function __construct(
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $until = null,
    ) {
    }
}
