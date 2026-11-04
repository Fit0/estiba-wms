<?php

declare(strict_types=1);

namespace App\Outbound\Application\Contract;

use App\Shared\Domain\TenantId;

/**
 * The figures of orders and picking that other contexts show on a dashboard (ADR 0003, ADR 0043).
 */
interface OutboundKpis
{
    /**
     * @param \DateTimeImmutable $from  start of the period, included
     * @param \DateTimeImmutable $until end of the period, not included
     */
    public function between(TenantId $tenantId, \DateTimeImmutable $from, \DateTimeImmutable $until): OutboundFigures;
}
