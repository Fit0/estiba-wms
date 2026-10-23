<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine;

use App\Inventory\Domain\ReservationId;
use App\Inventory\Domain\ReservationReference;
use App\Inventory\Domain\StockReservation;
use App\Inventory\Domain\StockReservationRepository;
use App\Shared\Domain\TenantId;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Stages the reservation with the unit of work. It does not flush: the command bus does.
 */
final readonly class DoctrineStockReservationRepository implements StockReservationRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[\Override]
    public function find(ReservationId $id): ?StockReservation
    {
        return $this->entityManager->find(StockReservation::class, $id);
    }

    #[\Override]
    public function findByReference(TenantId $tenantId, ReservationReference $reference): ?StockReservation
    {
        $found = $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from(StockReservation::class, 'r')
            ->where('r.tenantId = :tenant')
            ->andWhere('r.reference = :reference')
            ->setParameter('tenant', $tenantId, 'tenant_id')
            ->setParameter('reference', $reference, 'reservation_reference')
            ->getQuery()
            ->getOneOrNullResult();

        return $found instanceof StockReservation ? $found : null;
    }

    #[\Override]
    public function save(StockReservation $reservation): void
    {
        $this->entityManager->persist($reservation);
    }
}
