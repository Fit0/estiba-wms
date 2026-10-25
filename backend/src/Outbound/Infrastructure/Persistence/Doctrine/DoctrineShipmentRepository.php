<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Persistence\Doctrine;

use App\Outbound\Domain\OrderId;
use App\Outbound\Domain\Shipment;
use App\Outbound\Domain\ShipmentId;
use App\Outbound\Domain\ShipmentRepository;
use App\Outbound\Domain\ShipmentStatus;
use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;
use App\Shared\Domain\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Stages the shipment with the unit of work. It does not flush: the command bus does.
 */
final readonly class DoctrineShipmentRepository implements ShipmentRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[\Override]
    public function find(ShipmentId $id): ?Shipment
    {
        return $this->entityManager->find(Shipment::class, $id);
    }

    #[\Override]
    public function findByOrder(OrderId $orderId): ?Shipment
    {
        return $this->entityManager->getRepository(Shipment::class)->findOneBy(['orderId' => $orderId]);
    }

    #[\Override]
    public function search(TenantId $tenantId, ?ShipmentStatus $status, PageRequest $page): Page
    {
        $total = $this->filtered($tenantId, $status)
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<Shipment> $items */
        $items = $this->filtered($tenantId, $status)
            ->select('s')
            ->orderBy('s.startedAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setFirstResult($page->offset())
            ->setMaxResults($page->itemsPerPage)
            ->getQuery()
            ->getResult();

        return new Page($items, (int) $total, $page);
    }

    #[\Override]
    public function save(Shipment $shipment): void
    {
        $this->entityManager->persist($shipment);
    }

    private function filtered(TenantId $tenantId, ?ShipmentStatus $status): QueryBuilder
    {
        $query = $this->entityManager->createQueryBuilder()
            ->from(Shipment::class, 's')
            ->where('s.tenantId = :tenant')
            ->setParameter('tenant', $tenantId, 'tenant_id');

        if ($status instanceof ShipmentStatus) {
            $query->andWhere('s.status = :status')->setParameter('status', $status);
        }

        return $query;
    }
}
