<?php

declare(strict_types=1);

namespace App\Analytics\Application\GetKpis;

use App\Analytics\Domain\KpiPeriod;

/**
 * Every figure of the dashboard. A ratio that has nothing to divide by is null, never zero: "no
 * pick lists were completed" is not "picking is infinitely slow".
 *
 * Orders open, units and locations are a picture of now. Orders registered and shipped, and the
 * picking figures, count what happened inside the period; picking is measured on the pick lists
 * completed in it.
 */
final readonly class KpiReport
{
    public function __construct(
        public KpiPeriod $period,
        public int $ordersOpen,
        public int $ordersRegistered,
        public int $ordersShipped,
        public int $pickListsCompleted,
        public int $linesPicked,
        public int $unitsPicked,
        public ?float $linesPerHour,
        public ?float $averageListMinutes,
        public int $shortPickLines,
        public int $unitsShort,
        public ?float $shortPickPercent,
        public int $unitsOnHand,
        public int $unitsAvailable,
        public int $unitsQuarantine,
        public int $unitsDamaged,
        public int $unitsOnHold,
        public int $storageLocations,
        public int $occupiedLocations,
        public ?float $occupancyPercent,
    ) {
    }
}
