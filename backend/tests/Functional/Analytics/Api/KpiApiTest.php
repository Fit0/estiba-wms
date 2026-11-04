<?php

declare(strict_types=1);

namespace App\Tests\Functional\Analytics\Api;

use App\Identity\Domain\Role;
use App\Inventory\Application\RebuildStockOverview\RebuildStockOverview;
use App\Inventory\Domain\StockStatus;
use App\Shared\Application\Bus\CommandBus;
use App\Tests\Functional\Outbound\Api\ShipmentApiTestCase;
use App\Tests\Unit\Support\Ids;

/**
 * The operating figures of the warehouse, read from the data of each context (E07-04, ADR 0043).
 */
final class KpiApiTest extends ShipmentApiTestCase
{
    public function testAnEmptyWarehouseHasZerosAndLeavesOutTheRatiosItCannotWorkOut(): void
    {
        $kpis = $this->request('GET', '/api/kpis');

        $this->assertStatus(200);
        self::assertSame(['open' => 0, 'registered' => 0, 'shipped' => 0], $kpis['orders']);
        self::assertSame(
            ['listsCompleted' => 0, 'linesPicked' => 0, 'unitsPicked' => 0, 'shortPickLines' => 0, 'unitsShort' => 0],
            $kpis['picking'],
        );
        self::assertSame(['onHand' => 0, 'available' => 0, 'quarantine' => 0, 'damaged' => 0, 'onHold' => 0], $kpis['stock']);
        // The reserve and pick face locations of the two warehouses, and the shelf of the test.
        self::assertSame(['storage' => 5, 'occupied' => 0, 'occupancyPercent' => 0.0], $kpis['locations']);
    }

    public function testMeasuresAPickedOrder(): void
    {
        $this->stock($this->shirtId, $this->reserveId, 7, received: '2026-12-01');
        $this->pickedOrder();
        $this->startedHalfAnHourBeforeItEnded();
        // The test put the 7 units on the shelf by hand, so the overview does not know them yet.
        self::getContainer()->get(CommandBus::class)->dispatch(new RebuildStockOverview());

        $kpis = $this->request('GET', '/api/kpis');

        $this->assertStatus(200);
        self::assertSame(['open' => 1, 'registered' => 1, 'shipped' => 0], $kpis['orders']);
        self::assertSame(
            ['listsCompleted' => 1, 'linesPicked' => 1, 'unitsPicked' => 10, 'linesPerHour' => 2.0, 'averageListMinutes' => 30.0, 'shortPickLines' => 0, 'unitsShort' => 0, 'shortPickPercent' => 0.0],
            $kpis['picking'],
        );
        // The 10 picked units wait at staging, and 7 are still on a shelf.
        self::assertSame(['onHand' => 17, 'available' => 17, 'quarantine' => 0, 'damaged' => 0, 'onHold' => 0], $kpis['stock']);
        self::assertSame(['storage' => 5, 'occupied' => 1, 'occupancyPercent' => 20.0], $kpis['locations']);
    }

    public function testSplitsTheStockByStatus(): void
    {
        $this->stock($this->shirtId, $this->reserveId, 8);
        $this->stock($this->shirtId, $this->secondReserveId, 3, status: StockStatus::Damaged);
        $this->stock($this->shirtId, $this->pickFaceId, 2, status: StockStatus::Quarantine);
        $this->stock($this->shirtId, $this->shelf, 1, status: StockStatus::OnHold);
        self::getContainer()->get(CommandBus::class)->dispatch(new RebuildStockOverview());

        $kpis = $this->request('GET', '/api/kpis');

        self::assertSame(['onHand' => 14, 'available' => 8, 'quarantine' => 2, 'damaged' => 3, 'onHold' => 1], $kpis['stock']);
        self::assertSame(['storage' => 5, 'occupied' => 4, 'occupancyPercent' => 80.0], $kpis['locations']);
    }

    public function testCountsTheUnitsNotFoundAsAShortPick(): void
    {
        $this->stock($this->shirtId, $this->shelf, 10, received: '2026-09-01');
        $order = $this->allocate($this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 10]]));
        $wave = $this->request('POST', '/api/waves', ['orderIds' => [$this->id($order)]]);
        $released = $this->request('POST', '/api/waves/'.$this->id($wave).'/release');
        $listId = $this->listOf($released, 'pickListIds')[0] ?? null;
        self::assertIsString($listId);
        $this->request('POST', '/api/pick-lists/'.$listId.'/start');
        $this->request('POST', '/api/pick-lists/'.$listId.'/lines/1/confirm', ['location' => 'A-03-01', 'gtin' => self::SHIRT_GTIN, 'units' => 7, 'reason' => 'Solo había siete']);
        $this->assertStatus(200);

        $picking = $this->request('GET', '/api/kpis')['picking'];

        self::assertIsArray($picking);
        self::assertSame(1, $picking['listsCompleted']);
        self::assertSame(7, $picking['unitsPicked']);
        self::assertSame(1, $picking['shortPickLines']);
        self::assertSame(3, $picking['unitsShort']);
        self::assertSame(30.0, $picking['shortPickPercent']);
    }

    public function testCountsTheOrdersShippedInThePeriod(): void
    {
        $shipment = $this->startPacking();
        $this->closeParcel($shipment, 10);
        $this->request('POST', '/api/shipments/'.$this->id($shipment).'/complete');
        $this->request('POST', '/api/shipments/'.$this->id($shipment).'/dispatch', ['carrier' => 'SEUR']);
        $this->assertStatus(200);

        $kpis = $this->request('GET', '/api/kpis');

        self::assertSame(['open' => 0, 'registered' => 1, 'shipped' => 1], $kpis['orders'], 'A shipped order is no longer open.');
    }

    public function testOnlyCountsWhatHappenedInsideThePeriodExceptWhatIsOpenNow(): void
    {
        $this->pickedOrder();
        $this->startedHalfAnHourBeforeItEnded();

        $later = $this->request('GET', '/api/kpis', query: ['from' => '2030-01-01', 'until' => '2030-01-08']);
        $now = $this->request('GET', '/api/kpis', query: ['from' => '2026-01-01', 'until' => '2026-12-31']);

        self::assertSame(['open' => 1, 'registered' => 0, 'shipped' => 0], $later['orders']);
        self::assertSame(0, $this->picking($later)['listsCompleted']);
        self::assertArrayNotHasKey('linesPerHour', $this->picking($later));
        self::assertSame(['open' => 1, 'registered' => 1, 'shipped' => 0], $now['orders']);
    }

    public function testTheDefaultPeriodIsTheLastSevenDays(): void
    {
        $kpis = $this->request('GET', '/api/kpis');

        $from = new \DateTimeImmutable($this->text($kpis, 'from'));
        $until = new \DateTimeImmutable($this->text($kpis, 'until'));
        self::assertSame(7 * 24 * 3600, $until->getTimestamp() - $from->getTimestamp());
    }

    public function testRefusesAnInvalidPeriod(): void
    {
        $backwards = $this->request('GET', '/api/kpis', query: ['from' => '2026-10-02', 'until' => '2026-10-01']);
        $this->assertProblem(422, 'analytics.period_invalid', $backwards);
        self::assertSame('not_forward', $this->parameter($backwards, 'reason'));

        $tooLong = $this->request('GET', '/api/kpis', query: ['from' => '2024-01-01', 'until' => '2026-01-01']);
        $this->assertProblem(422, 'analytics.period_invalid', $tooLong);
        self::assertSame('too_long', $this->parameter($tooLong, 'reason'));
    }

    public function testRefusesADateItCannotRead(): void
    {
        $this->request('GET', '/api/kpis', query: ['from' => 'yesterday']);

        $this->assertStatus(400);
    }

    public function testAcceptsADateWithTime(): void
    {
        $kpis = $this->request('GET', '/api/kpis', query: ['from' => '2026-10-24T08:00:00+00:00', 'until' => '2026-10-24T20:00:00+00:00']);

        $this->assertStatus(200);
        self::assertSame('2026-10-24T08:00:00+00:00', $kpis['from']);
        self::assertSame('2026-10-24T20:00:00+00:00', $kpis['until']);
    }

    public function testAnOperatorCannotSeeTheFigures(): void
    {
        $this->role = Role::Operator;

        $this->request('GET', '/api/kpis');

        $this->assertStatus(403);
    }

    public function testNeedsASession(): void
    {
        $this->request('GET', '/api/kpis', tenant: '');

        $this->assertStatus(401);
    }

    public function testAnotherTenantSeesNothingOfThisOne(): void
    {
        $this->pickedOrder();

        $kpis = $this->request('GET', '/api/kpis', tenant: Ids::next());

        self::assertSame(['open' => 0, 'registered' => 0, 'shipped' => 0], $kpis['orders']);
        self::assertSame(0, $this->picking($kpis)['listsCompleted']);
        self::assertSame(['onHand' => 0, 'available' => 0, 'quarantine' => 0, 'damaged' => 0, 'onHold' => 0], $kpis['stock']);
        self::assertSame(['storage' => 0, 'occupied' => 0], $kpis['locations'], 'Without storage locations there is no occupancy to speak of.');
    }

    /**
     * The test runs in a blink, so the list is moved back in time to have a duration to measure.
     */
    private function startedHalfAnHourBeforeItEnded(): void
    {
        $this->entityManager()->getConnection()->executeStatement("UPDATE pick_lists SET started_at = completed_at - INTERVAL '30 minutes'");
    }

    /**
     * @param array<array-key, mixed> $kpis
     *
     * @return array<array-key, mixed>
     */
    private function picking(array $kpis): array
    {
        $picking = $kpis['picking'] ?? null;
        self::assertIsArray($picking);

        return $picking;
    }
}
