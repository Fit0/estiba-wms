<?php

declare(strict_types=1);

namespace App\Tests\Unit\Warehouse\Domain;

use App\Tests\Unit\Support\AssertsDomainErrors;
use App\Warehouse\Domain\BlockReason;
use App\Warehouse\Domain\Event\LocationBlocked;
use App\Warehouse\Domain\Event\LocationCreated;
use App\Warehouse\Domain\Event\LocationUnblocked;
use App\Warehouse\Domain\Location;
use App\Warehouse\Domain\LocationRepository;
use App\Warehouse\Domain\LocationStatus;
use PHPUnit\Framework\TestCase;

final class LocationTest extends TestCase
{
    use AssertsDomainErrors;

    public function testANewLocationIsActiveAndEligibleForStorage(): void
    {
        $location = WarehouseMother::location();

        self::assertSame(LocationStatus::Active, $location->status());
        self::assertNull($location->blockReason());
        self::assertTrue($location->isEligibleForStorage());
    }

    // WH-06: a blocked location is never offered for putaway or allocation.
    public function testABlockedLocationIsNotEligibleForStorage(): void
    {
        $location = WarehouseMother::location();
        $location->pullEvents();

        $location->block(BlockReason::fromString('Damaged rack'), WarehouseMother::at());

        self::assertSame(LocationStatus::Blocked, $location->status());
        self::assertSame('Damaged rack', $location->blockReason()?->value);
        self::assertFalse($location->isEligibleForStorage());

        $events = $location->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(LocationBlocked::class, $events[0]);
        self::assertSame('Damaged rack', $events[0]->reason->value);
        self::assertSame('A-03-02', $events[0]->code->value);
    }

    public function testBlockingAnAlreadyBlockedLocationIsRejected(): void
    {
        $location = WarehouseMother::location();
        $location->block(BlockReason::fromString('Damaged rack'), WarehouseMother::at());
        $location->pullEvents();

        self::assertDomainError(
            'warehouse.location_already_blocked',
            ['code' => 'A-03-02'],
            static fn () => $location->block(BlockReason::fromString('Another reason'), WarehouseMother::at()),
        );
        self::assertSame('Damaged rack', $location->blockReason()?->value);
        self::assertSame([], $location->pullEvents());
    }

    public function testUnblockingMakesTheLocationEligibleAgain(): void
    {
        $location = WarehouseMother::location();
        $location->block(BlockReason::fromString('Damaged rack'), WarehouseMother::at());
        $location->pullEvents();

        $location->unblock(WarehouseMother::at());

        self::assertSame(LocationStatus::Active, $location->status());
        self::assertNull($location->blockReason());
        self::assertTrue($location->isEligibleForStorage());

        $events = $location->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(LocationUnblocked::class, $events[0]);
    }

    public function testUnblockingALocationThatIsNotBlockedIsRejected(): void
    {
        $location = WarehouseMother::location();
        $location->pullEvents();

        self::assertDomainError(
            'warehouse.location_not_blocked',
            ['code' => 'A-03-02'],
            static fn () => $location->unblock(WarehouseMother::at()),
        );
        self::assertSame([], $location->pullEvents());
    }

    public function testRaisesEventsInTheOrderThingsHappened(): void
    {
        $location = WarehouseMother::location();
        $location->block(BlockReason::fromString('Damaged rack'), WarehouseMother::at());
        $location->unblock(WarehouseMother::at());

        $events = $location->pullEvents();

        self::assertCount(3, $events);
        self::assertInstanceOf(LocationCreated::class, $events[0]);
        self::assertInstanceOf(LocationBlocked::class, $events[1]);
        self::assertInstanceOf(LocationUnblocked::class, $events[2]);
    }

    // WH-07: locations are blocked, never deleted, because stock history keeps pointing at them.
    public function testLocationsCannotBeDeleted(): void
    {
        foreach ([Location::class, LocationRepository::class] as $type) {
            foreach (new \ReflectionClass($type)->getMethods() as $method) {
                self::assertDoesNotMatchRegularExpression(
                    '/^(delete|remove|destroy|purge)/i',
                    $method->getName(),
                    \sprintf('%s::%s() would allow deleting a location (WH-07).', $type, $method->getName()),
                );
            }
        }
    }
}
