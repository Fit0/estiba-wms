<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Persistence\Doctrine;

use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;
use App\Warehouse\Domain\Location;
use App\Warehouse\Domain\LocationCode;
use App\Warehouse\Domain\LocationId;
use App\Warehouse\Domain\LocationRepository;
use App\Warehouse\Domain\LocationStatus;
use App\Warehouse\Domain\WarehouseId;
use App\Warehouse\Domain\ZoneId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

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
    public function search(WarehouseId $warehouseId, ?ZoneId $zoneId, ?LocationStatus $status, PageRequest $page): Page
    {
        $total = $this->filtered($warehouseId, $zoneId, $status)
            ->select('COUNT(l.id)')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<Location> $items */
        $items = $this->filtered($warehouseId, $zoneId, $status)
            ->select('l')
            ->orderBy('l.code')
            ->setFirstResult($page->offset())
            ->setMaxResults($page->itemsPerPage)
            ->getQuery()
            ->getResult();

        return new Page($items, (int) $total, $page);
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

    private function filtered(WarehouseId $warehouseId, ?ZoneId $zoneId, ?LocationStatus $status): QueryBuilder
    {
        $query = $this->entityManager->createQueryBuilder()
            ->from(Location::class, 'l')
            ->where('l.warehouseId = :warehouse')
            ->setParameter('warehouse', $warehouseId, 'warehouse_id');

        if ($zoneId instanceof ZoneId) {
            $query->andWhere('l.zoneId = :zone')->setParameter('zone', $zoneId, 'zone_id');
        }

        if ($status instanceof LocationStatus) {
            $query->andWhere('l.status = :status')->setParameter('status', $status);
        }

        return $query;
    }
}
