<?php

declare(strict_types=1);

namespace App\Tests\Functional\Warehouse\Persistence;

use App\Shared\Domain\StorageClass;
use App\Tests\Unit\Warehouse\Domain\WarehouseMother;
use App\Warehouse\Domain\DisplayName;
use App\Warehouse\Domain\PickSequence;
use App\Warehouse\Domain\WarehouseCode;
use App\Warehouse\Domain\ZoneCode;
use App\Warehouse\Infrastructure\Persistence\Doctrine\DoctrineWarehouseRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class DoctrineWarehouseRepositoryTest extends PersistenceTestCase
{
    private DoctrineWarehouseRepository $repository;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new DoctrineWarehouseRepository($this->entityManager);
    }

    public function testFindsASavedWarehouseWithItsZones(): void
    {
        $warehouse = WarehouseMother::warehouse();
        $fastId = WarehouseMother::zoneId();
        WarehouseMother::addZone($warehouse, $fastId, 'FAST');
        $warehouse->addZone(
            WarehouseMother::zoneId(),
            ZoneCode::fromString('CHILL'),
            DisplayName::fromString('Cámara de frío'),
            StorageClass::Chilled,
            PickSequence::of(2),
            WarehouseMother::at(),
        );
        $this->repository->save($warehouse);
        $this->flushAndForget();

        $found = $this->repository->find($warehouse->id());

        self::assertNotNull($found);
        self::assertTrue($found->id()->equals($warehouse->id()));
        self::assertTrue($found->tenantId()->equals($warehouse->tenantId()));
        self::assertSame('MAD1', $found->code()->value);
        self::assertSame('Madrid central warehouse', $found->name()->value);
        self::assertCount(2, $found->zones());
        self::assertTrue($found->zones()[0]->id->equals($fastId));
        self::assertSame('CHILL', $found->zones()[1]->code->value);
        self::assertSame('Cámara de frío', $found->zones()[1]->name->value);
        self::assertSame(StorageClass::Chilled, $found->zones()[1]->storageClass);
        self::assertSame(2, $found->zones()[1]->pickSequence->value);
    }

    public function testFindsNothingForAnUnknownWarehouse(): void
    {
        self::assertNull($this->repository->find(WarehouseMother::warehouseId()));
    }

    public function testSavesAZoneAddedToAnExistingWarehouse(): void
    {
        $warehouse = WarehouseMother::warehouse();
        $this->repository->save($warehouse);
        $this->flushAndForget();

        $loaded = $this->repository->find($warehouse->id());
        self::assertNotNull($loaded);
        WarehouseMother::addZone($loaded, WarehouseMother::zoneId());
        $this->flushAndForget();

        $reloaded = $this->repository->find($warehouse->id());
        self::assertNotNull($reloaded);
        self::assertCount(1, $reloaded->zones());
    }

    public function testReportsACodeAsTakenOnlyWithinTheSameTenant(): void
    {
        $warehouse = WarehouseMother::warehouse();
        $this->repository->save($warehouse);
        $this->flushAndForget();
        $code = WarehouseCode::fromString('MAD1');

        self::assertTrue($this->repository->isTaken($warehouse->tenantId(), $code));
        self::assertFalse($this->repository->isTaken(WarehouseMother::tenantId(), $code));
        self::assertFalse($this->repository->isTaken($warehouse->tenantId(), WarehouseCode::fromString('BCN1')));
    }

    /**
     * WH-01: the database backs the registry check against two requests racing.
     */
    public function testRejectsTwoWarehousesWithTheSameCodeForATenant(): void
    {
        $first = WarehouseMother::warehouse();
        $second = WarehouseMother::warehouse($first->tenantId());
        $this->repository->save($first);
        $this->repository->save($second);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->entityManager->flush();
    }
}
