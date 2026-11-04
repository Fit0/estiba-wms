<?php

declare(strict_types=1);

namespace App\Analytics\Application\GetKpis;

use App\Analytics\Domain\Exception\InvalidKpiPeriod;
use App\Analytics\Domain\KpiPeriod;
use App\Inventory\Application\Contract\StockKpis;
use App\Outbound\Application\Contract\OutboundKpis;
use App\Shared\Application\Bus\QueryHandler;
use App\Shared\Application\Clock;
use App\Shared\Application\CurrentTenant;
use App\Warehouse\Application\Contract\LocationCensus;

/**
 * Asks each context for the figures it owns and works out the ratios (ADR 0043). Nothing is stored:
 * every call reads the data as it is.
 */
final readonly class GetKpisHandler implements QueryHandler
{
    private const int DEFAULT_DAYS = 7;

    public function __construct(
        private OutboundKpis $outbound,
        private StockKpis $stock,
        private LocationCensus $locations,
        private CurrentTenant $tenant,
        private Clock $clock,
    ) {
    }

    /**
     * @throws InvalidKpiPeriod
     */
    public function __invoke(GetKpis $query): KpiReport
    {
        $now = $this->clock->now();
        $period = match (true) {
            $query->from instanceof \DateTimeImmutable && $query->until instanceof \DateTimeImmutable => KpiPeriod::between($query->from, $query->until),
            $query->from instanceof \DateTimeImmutable => KpiPeriod::between($query->from, $now),
            $query->until instanceof \DateTimeImmutable => KpiPeriod::between($query->until->modify(\sprintf('-%d days', self::DEFAULT_DAYS)), $query->until),
            default => KpiPeriod::lastDays(self::DEFAULT_DAYS, $now),
        };

        $tenantId = $this->tenant->id();
        $outbound = $this->outbound->between($tenantId, $period->from, $period->until);
        $stock = $this->stock->summary($tenantId);
        $storage = $this->locations->activeStorageLocationIds($tenantId);
        $occupied = $this->stock->occupiedAmong($tenantId, $storage);

        $pickingHours = $outbound->pickingSeconds / 3600;
        $asked = $outbound->unitsPicked + $outbound->unitsShort;

        return new KpiReport(
            $period,
            $outbound->ordersOpen,
            $outbound->ordersRegistered,
            $outbound->ordersShipped,
            $outbound->pickListsCompleted,
            $outbound->linesPicked,
            $outbound->unitsPicked,
            $pickingHours > 0 ? $this->round($outbound->linesPicked / $pickingHours) : null,
            $outbound->pickListsCompleted > 0 ? $this->round($outbound->pickingSeconds / 60 / $outbound->pickListsCompleted) : null,
            $outbound->shortPickLines,
            $outbound->unitsShort,
            $asked > 0 ? $this->round(100 * $outbound->unitsShort / $asked) : null,
            $stock->onHand,
            $stock->available,
            $stock->quarantine,
            $stock->damaged,
            $stock->onHold,
            \count($storage),
            $occupied,
            [] !== $storage ? $this->round(100 * $occupied / \count($storage)) : null,
        );
    }

    private function round(float $value): float
    {
        return round($value, 1);
    }
}
