<?php

declare(strict_types=1);

namespace App\Tests\Functional\Warehouse\Persistence;

use App\Tests\Unit\Warehouse\Domain\InMemoryLocationCodes;
use App\Tests\Unit\Warehouse\Domain\WarehouseMother;
use App\Warehouse\Domain\BlockReason;
use App\Warehouse\Domain\LocationCapacity;
use App\Warehouse\Domain\LocationCode;
use App\Warehouse\Domain\LocationStatus;
use App\Warehouse\Domain\LocationType;
use App\Warehouse\Domain\PickSequence;
use App\Warehouse\Infrastructure\Persistence\Doctrine\DoctrineLocationRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class DoctrineLocationRepositoryTest extends PersistenceTestCase
{
    private DoctrineLocationRepository $repository;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new DoctrineLocationRepository($this->entityManager);
    }

    public function testFindsASavedLocation(): void
    {
        $location = WarehouseMother::location('A-03-02');
        $this->repository->save($location);
        $this->flushAndForget();

        $found = $this->repository->find($location->id());

        self::assertNotNull($found);
        self::assertTrue($found->id()->equals($location->id()));
        self::assertTrue($found->tenantId()->equals($location->tenantId()));
        self::assertTrue($found->warehouseId()->equals($location->warehouseId()));
        self::assertTrue($found->zoneId()->equals($location->zoneId()));
        self::assertSame('A-03-02', $found->code()->value);
        self::assertSame(LocationType::Reserve, $found->type());
        self::assertSame(10, $found->pickSequence()->value);
        self::assertSame(LocationStatus::Active, $found->status());
        self::assertNull($found->blockReason());
        self::assertTrue($found->isEligibleForStorage());
    }

    public function testKeepsEveryDeclaredCapacityLimit(): void
    {
        $location = WarehouseMother::location();
        $this->repository->save($location);
        $this->flushAndForget();

        $found = $this->repository->find($location->id());

        self::assertNotNull($found);
        self::assertEquals(LocationCapacity::of(maxWeightKg: 1000, maxPalletPositions: 1), $found->capacity());
        self::assertTrue($found->capacity()->hasLimits());
    }

    public function testKeepsALocationWithoutLimitsAsUnlimited(): void
    {
        $warehouse = WarehouseMother::warehouse();
        $zoneId = WarehouseMother::zoneId();
        WarehouseMother::addZone($warehouse, $zoneId);
        $location = $warehouse->defineLocation(
            WarehouseMother::locationId(),
            $zoneId,
            LocationCode::fromString('DK-01-01'),
            LocationType::Dock,
            LocationCapacity::unlimited(),
            PickSequence::of(0),
            new InMemoryLocationCodes(),
            WarehouseMother::at(),
        );
        $this->repository->save($location);
        $this->flushAndForget();

        $found = $this->repository->find($location->id());

        self::assertNotNull($found);
        self::assertEquals(LocationCapacity::unlimited(), $found->capacity());
    }

    public function testKeepsABlockedLocationAndItsReason(): void
    {
        $location = WarehouseMother::location();
        $location->block(BlockReason::fromString('Rack damaged by a forklift'), WarehouseMother::at());
        $this->repository->save($location);
        $this->flushAndForget();

        $found = $this->repository->find($location->id());

        self::assertNotNull($found);
        self::assertSame(LocationStatus::Blocked, $found->status());
        self::assertSame('Rack damaged by a forklift', $found->blockReason()?->value);
        self::assertFalse($found->isEligibleForStorage());
    }

    public function testSavesTheUnblockOfAnExistingLocation(): void
    {
        $location = WarehouseMother::location();
        $location->block(BlockReason::fromString('Inventory count'), WarehouseMother::at());
        $this->repository->save($location);
        $this->flushAndForget();

        $loaded = $this->repository->find($location->id());
        self::assertNotNull($loaded);
        $loaded->unblock(WarehouseMother::at());
        $this->flushAndForget();

        $reloaded = $this->repository->find($location->id());
        self::assertNotNull($reloaded);
        self::assertSame(LocationStatus::Active, $reloaded->status());
        self::assertNull($reloaded->blockReason());
    }

    public function testFindsNothingForAnUnknownLocation(): void
    {
        self::assertNull($this->repository->find(WarehouseMother::locationId()));
    }

    public function testReportsACodeAsTakenOnlyWithinTheSameWarehouse(): void
    {
        $location = WarehouseMother::location('A-03-02');
        $this->repository->save($location);
        $this->flushAndForget();
        $code = LocationCode::fromString('A-03-02');

        self::assertTrue($this->repository->isTaken($location->warehouseId(), $code));
        self::assertFalse($this->repository->isTaken(WarehouseMother::warehouseId(), $code));
        self::assertFalse($this->repository->isTaken($location->warehouseId(), LocationCode::fromString('B-01-01')));
    }

    /**
     * WH-03: the database backs the registry check against two requests racing.
     */
    public function testRejectsTwoLocationsWithTheSameCodeInAWarehouse(): void
    {
        $warehouse = WarehouseMother::warehouse();
        $zoneId = WarehouseMother::zoneId();
        WarehouseMother::addZone($warehouse, $zoneId);
        $this->repository->save(WarehouseMother::defineLocation($warehouse, $zoneId, 'A-03-02'));
        $this->repository->save(WarehouseMother::defineLocation($warehouse, $zoneId, 'A-03-02'));

        $this->expectException(UniqueConstraintViolationException::class);

        $this->entityManager->flush();
    }
}
