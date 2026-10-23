<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Persistence\Doctrine;

use App\Outbound\Domain\Order;
use App\Outbound\Domain\OrderId;
use App\Outbound\Domain\OrderReference;
use App\Outbound\Domain\OrderRepository;
use App\Outbound\Domain\OrderStatus;
use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;
use App\Shared\Domain\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Stages the order with the unit of work. It does not flush: the command bus does.
 */
final readonly class DoctrineOrderRepository implements OrderRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[\Override]
    public function find(OrderId $id): ?Order
    {
        return $this->entityManager->find(Order::class, $id);
    }

    /**
     * The most urgent orders come first, and among them the ones that have to leave first. The
     * priority is stored by name, so the order of the urgency is spelled out here.
     */
    #[\Override]
    public function search(TenantId $tenantId, ?OrderStatus $status, PageRequest $page): Page
    {
        $total = $this->filtered($tenantId, $status)
            ->select('COUNT(o.id)')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<Order> $items */
        $items = $this->filtered($tenantId, $status)
            ->select('o')
            ->addSelect("CASE o.priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END AS HIDDEN urgency")
            ->orderBy('urgency')
            ->addOrderBy('o.cutOffAt')
            ->addOrderBy('o.reference')
            ->setFirstResult($page->offset())
            ->setMaxResults($page->itemsPerPage)
            ->getQuery()
            ->getResult();

        return new Page($items, (int) $total, $page);
    }

    #[\Override]
    public function save(Order $order): void
    {
        $this->entityManager->persist($order);
    }

    #[\Override]
    public function isReferenceTaken(TenantId $tenantId, OrderReference $reference): bool
    {
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(o.id)')
            ->from(Order::class, 'o')
            ->where('o.tenantId = :tenant')
            ->andWhere('o.reference = :reference')
            ->setParameter('tenant', $tenantId, 'tenant_id')
            ->setParameter('reference', $reference, 'order_reference')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    private function filtered(TenantId $tenantId, ?OrderStatus $status): QueryBuilder
    {
        $query = $this->entityManager->createQueryBuilder()
            ->from(Order::class, 'o')
            ->where('o.tenantId = :tenant')
            ->setParameter('tenant', $tenantId, 'tenant_id');

        if ($status instanceof OrderStatus) {
            $query->andWhere('o.status = :status')->setParameter('status', $status);
        }

        return $query;
    }
}
