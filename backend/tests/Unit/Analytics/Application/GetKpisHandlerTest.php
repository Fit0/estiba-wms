<?php

declare(strict_types=1);

namespace App\Tests\Unit\Analytics\Application;

use App\Analytics\Application\GetKpis\GetKpis;
use App\Analytics\Application\GetKpis\GetKpisHandler;
use App\Inventory\Application\Contract\StockKpis;
use App\Inventory\Application\Contract\StockSummary;
use App\Outbound\Application\Contract\OutboundFigures;
use App\Outbound\Application\Contract\OutboundKpis;
use App\Shared\Application\Clock;
use App\Shared\Application\CurrentTenant;
use App\Shared\Domain\TenantId;
use App\Tests\Unit\Support\AssertsDomainErrors;
use App\Tests\Unit\Support\Ids;
use App\Warehouse\Application\Contract\LocationCensus;
use PHPUnit\Framework\TestCase;

final class GetKpisHandlerTest extends TestCase
{
    use AssertsDomainErrors;

    private const string NOW = '2026-11-03T10:00:00+00:00';

    public function testWorksOutTheRatiosFromTheFiguresOfEachContext(): void
    {
        $report = $this->handler(
            new OutboundFigures(ordersOpen: 4, ordersRegistered: 9, ordersShipped: 7, pickListsCompleted: 3, linesPicked: 45, unitsPicked: 90, shortPickLines: 2, unitsShort: 10, pickingSeconds: 5400),
            new StockSummary(onHand: 500, available: 420, quarantine: 30, damaged: 20, onHold: 30),
            storage: ['a', 'b', 'c', 'd'],
            occupied: 3,
        )(new GetKpis());

        self::assertSame(4, $report->ordersOpen);
        self::assertSame(9, $report->ordersRegistered);
        self::assertSame(7, $report->ordersShipped);
        self::assertSame(30.0, $report->linesPerHour, '45 stops in an hour and a half');
        self::assertSame(30.0, $report->averageListMinutes, 'An hour and a half over three lists');
        self::assertSame(10.0, $report->shortPickPercent, '10 units missing out of the 100 asked');
        self::assertSame(20, $report->unitsDamaged);
        self::assertSame(4, $report->storageLocations);
        self::assertSame(3, $report->occupiedLocations);
        self::assertSame(75.0, $report->occupancyPercent);
    }

    public function testARatioWithNothingToDivideByIsNullAndNotZero(): void
    {
        $report = $this->handler(new OutboundFigures(0, 0, 0, 0, 0, 0, 0, 0, 0), new StockSummary(0, 0, 0, 0, 0), storage: [], occupied: 0)(new GetKpis());

        self::assertNull($report->linesPerHour);
        self::assertNull($report->averageListMinutes);
        self::assertNull($report->shortPickPercent);
        self::assertNull($report->occupancyPercent);
    }

    public function testWithoutAPeriodItIsTheLastSevenDays(): void
    {
        $report = $this->handler()(new GetKpis());

        self::assertEquals(new \DateTimeImmutable('2026-10-27T10:00:00+00:00'), $report->period->from);
        self::assertEquals(new \DateTimeImmutable(self::NOW), $report->period->until);
    }

    public function testASingleLimitCompletesTheOtherOne(): void
    {
        $from = new \DateTimeImmutable('2026-10-01T00:00:00+00:00');
        $until = new \DateTimeImmutable('2026-10-31T00:00:00+00:00');

        self::assertEquals(new \DateTimeImmutable(self::NOW), $this->handler()(new GetKpis(from: $from))->period->until);
        self::assertEquals($until->modify('-7 days'), $this->handler()(new GetKpis(until: $until))->period->from);
    }

    public function testRefusesAPeriodThatEndsBeforeItStarts(): void
    {
        $handler = $this->handler();

        self::assertDomainError(
            'analytics.period_invalid',
            ['reason' => 'not_forward', 'maxDays' => 366],
            static fn (): mixed => $handler(new GetKpis(new \DateTimeImmutable('2026-10-02T00:00:00+00:00'), new \DateTimeImmutable('2026-10-01T00:00:00+00:00'))),
        );
    }

    /**
     * @param list<string> $storage
     */
    private function handler(
        ?OutboundFigures $figures = null,
        ?StockSummary $summary = null,
        array $storage = [],
        int $occupied = 0,
    ): GetKpisHandler {
        $outbound = new readonly class($figures ?? new OutboundFigures(0, 0, 0, 0, 0, 0, 0, 0, 0)) implements OutboundKpis {
            public function __construct(private OutboundFigures $figures)
            {
            }

            #[\Override]
            public function between(TenantId $tenantId, \DateTimeImmutable $from, \DateTimeImmutable $until): OutboundFigures
            {
                return $this->figures;
            }
        };
        $stock = new readonly class($summary ?? new StockSummary(0, 0, 0, 0, 0), $occupied) implements StockKpis {
            public function __construct(private StockSummary $summary, private int $occupied)
            {
            }

            #[\Override]
            public function summary(TenantId $tenantId): StockSummary
            {
                return $this->summary;
            }

            #[\Override]
            public function occupiedAmong(TenantId $tenantId, array $locationIds): int
            {
                return $this->occupied;
            }
        };
        $locations = new readonly class($storage) implements LocationCensus {
            /** @param list<string> $ids */
            public function __construct(private array $ids)
            {
            }

            #[\Override]
            public function activeStorageLocationIds(TenantId $tenantId): array
            {
                return $this->ids;
            }
        };
        $tenant = new class implements CurrentTenant {
            #[\Override]
            public function id(): TenantId
            {
                return TenantId::fromString(Ids::next());
            }
        };
        $clock = new class implements Clock {
            #[\Override]
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-11-03T10:00:00+00:00');
            }
        };

        return new GetKpisHandler($outbound, $stock, $locations, $tenant, $clock);
    }
}
