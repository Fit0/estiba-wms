<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain;

use App\Inventory\Domain\LocationId;
use App\Inventory\Domain\StockStatus;
use App\Tests\Unit\Support\Ids;
use PHPUnit\Framework\TestCase;

final class StockKeyTest extends TestCase
{
    public function testTwoKeysWithTheSameFiveAttributesAreTheSameStockItem(): void
    {
        $productId = StockMother::productId();
        $locationId = LocationId::fromString(Ids::next());

        $first = StockMother::key($productId, $locationId, StockStatus::Available, 'L-1', new \DateTimeImmutable('2027-03-01T08:00:00+00:00'));
        $second = StockMother::key($productId, $locationId, StockStatus::Available, 'L-1', new \DateTimeImmutable('2027-03-01T23:30:00+00:00'));

        self::assertTrue($first->equals($second), 'The expiry date is a day: the hour does not matter.');
    }

    public function testAKeyWithoutLotOrExpiryEqualsAnotherWithout(): void
    {
        $productId = StockMother::productId();
        $locationId = LocationId::fromString(Ids::next());

        self::assertTrue(StockMother::key($productId, $locationId)->equals(StockMother::key($productId, $locationId)));
    }

    public function testEachAttributeTellsOneStockItemFromAnother(): void
    {
        $productId = StockMother::productId();
        $locationId = LocationId::fromString(Ids::next());
        $expiry = new \DateTimeImmutable('2027-03-01');
        $base = StockMother::key($productId, $locationId, StockStatus::Available, 'L-1', $expiry);

        self::assertFalse($base->equals(StockMother::key(StockMother::productId(), $locationId, StockStatus::Available, 'L-1', $expiry)), 'product');
        self::assertFalse($base->equals(StockMother::key($productId, LocationId::fromString(Ids::next()), StockStatus::Available, 'L-1', $expiry)), 'location');
        self::assertFalse($base->equals(StockMother::key($productId, $locationId, StockStatus::Available, 'L-2', $expiry)), 'lot');
        self::assertFalse($base->equals(StockMother::key($productId, $locationId, StockStatus::Available, null, $expiry)), 'no lot');
        self::assertFalse($base->equals(StockMother::key($productId, $locationId, StockStatus::Available, 'L-1', new \DateTimeImmutable('2027-03-02'))), 'expiry date');
        self::assertFalse($base->equals(StockMother::key($productId, $locationId, StockStatus::Available, 'L-1')), 'no expiry date');
        self::assertFalse($base->equals(StockMother::key($productId, $locationId, StockStatus::Quarantine, 'L-1', $expiry)), 'status');
    }
}
