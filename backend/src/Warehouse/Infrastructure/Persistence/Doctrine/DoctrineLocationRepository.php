<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Persistence\Doctrine;

use App\Warehouse\Domain\Location;
use App\Warehouse\Domain\LocationCode;
use App\Warehouse\Domain\LocationId;
use App\Warehouse\Domain\LocationRepository;
use App\Warehouse\Domain\WarehouseId;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Stages the location with the unit of work. It does not flush: the caller that owns the
 * transaction does.
 */
final readonly class DoctrineLocationRepository implements LocationRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[\Override]
    public function find(LocationId $id): ?Location
    {
        return $this->entityManager->find(Location::class, $id);
    }

    #[\Override]
    public function save(Location $location): void
    {
        $this->entityManager->persist($location);
    }

    #[\Override]
    public function isTaken(WarehouseId $warehouseId, LocationCode $code): bool
    {
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(l.id)')
            ->from(Location::class, 'l')
            ->where('l.warehouseId = :warehouse')
            ->andWhere('l.code = :code')
            ->setParameter('warehouse', $warehouseId, 'warehouse_id')
            ->setParameter('code', $code, 'location_code')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }
}
