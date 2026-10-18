<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Persistence\Doctrine;

use App\Shared\Domain\TenantId;
use App\Warehouse\Domain\Warehouse;
use App\Warehouse\Domain\WarehouseCode;
use App\Warehouse\Domain\WarehouseId;
use App\Warehouse\Domain\WarehouseRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Stages the warehouse with the unit of work. It does not flush: the caller that owns the
 * transaction does, so several aggregates can be saved together.
 */
final readonly class DoctrineWarehouseRepository implements WarehouseRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[\Override]
    public function find(WarehouseId $id): ?Warehouse
    {
        return $this->entityManager->find(Warehouse::class, $id);
    }

    #[\Override]
    public function save(Warehouse $warehouse): void
    {
        $this->entityManager->persist($warehouse);
    }

    #[\Override]
    public function isTaken(TenantId $tenantId, WarehouseCode $code): bool
    {
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(w.id)')
            ->from(Warehouse::class, 'w')
            ->where('w.tenantId = :tenant')
            ->andWhere('w.code = :code')
            ->setParameter('tenant', $tenantId, 'tenant_id')
            ->setParameter('code', $code, 'warehouse_code')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }
}
