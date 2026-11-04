<?php

declare(strict_types=1);

namespace App\Analytics\Infrastructure\Http\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Analytics\Application\GetKpis\KpiReport;
use App\Analytics\Infrastructure\Http\State\KpiProvider;

/**
 * The operating figures of the warehouse for a period: orders, picking, stock and storage
 * locations. A ratio with nothing to divide by is left out.
 */
#[ApiResource(
    shortName: 'Kpis',
    operations: [
        new Get(uriTemplate: '/kpis', provider: KpiProvider::class),
    ],
)]
final readonly class KpiResource
{
    public function __construct(
        public string $from,
        public string $until,
        public OrderKpisResource $orders,
        public PickingKpisResource $picking,
        public StockKpisResource $stock,
        public LocationKpisResource $locations,
        #[ApiProperty(identifier: true)]
        public string $id = 'kpis',
    ) {
    }

    public static function fromReport(KpiReport $report): self
    {
        return new self(
            $report->period->from->format(\DATE_ATOM),
            $report->period->until->format(\DATE_ATOM),
            new OrderKpisResource($report->ordersOpen, $report->ordersRegistered, $report->ordersShipped),
            new PickingKpisResource(
                $report->pickListsCompleted,
                $report->linesPicked,
                $report->unitsPicked,
                $report->linesPerHour,
                $report->averageListMinutes,
                $report->shortPickLines,
                $report->unitsShort,
                $report->shortPickPercent,
            ),
            new StockKpisResource($report->unitsOnHand, $report->unitsAvailable, $report->unitsQuarantine, $report->unitsDamaged, $report->unitsOnHold),
            new LocationKpisResource($report->storageLocations, $report->occupiedLocations, $report->occupancyPercent),
        );
    }
}
