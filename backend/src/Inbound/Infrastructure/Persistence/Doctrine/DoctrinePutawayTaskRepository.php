<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Persistence\Doctrine;

use App\Inbound\Domain\PutawayTask;
use App\Inbound\Domain\PutawayTaskId;
use App\Inbound\Domain\PutawayTaskRepository;
use App\Inbound\Domain\PutawayTaskStatus;
use App\Inbound\Domain\ReceiptId;
use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;
use App\Shared\Domain\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Stages the task with the unit of work. It does not flush: the caller that owns the transaction
 * does.
 */
final readonly class DoctrinePutawayTaskRepository implements PutawayTaskRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[\Override]
    public function find(PutawayTaskId $id): ?PutawayTask
    {
        return $this->entityManager->find(PutawayTask::class, $id);
    }

    #[\Override]
    public function search(TenantId $tenantId, ?ReceiptId $receiptId, ?PutawayTaskStatus $status, PageRequest $page): Page
    {
        $total = $this->filtered($tenantId, $receiptId, $status)
            ->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<PutawayTask> $items */
        $items = $this->filtered($tenantId, $receiptId, $status)
            ->select('t')
            ->orderBy('t.createdAt')
            ->addOrderBy('t.id')
            ->setFirstResult($page->offset())
            ->setMaxResults($page->itemsPerPage)
            ->getQuery()
            ->getResult();

        return new Page($items, (int) $total, $page);
    }

    #[\Override]
    public function save(PutawayTask $task): void
    {
        $this->entityManager->persist($task);
    }

    private function filtered(TenantId $tenantId, ?ReceiptId $receiptId, ?PutawayTaskStatus $status): QueryBuilder
    {
        $query = $this->entityManager->createQueryBuilder()
            ->from(PutawayTask::class, 't')
            ->where('t.tenantId = :tenant')
            ->setParameter('tenant', $tenantId, 'tenant_id');

        if ($receiptId instanceof ReceiptId) {
            $query->andWhere('t.receiptId = :receipt')->setParameter('receipt', $receiptId, 'receipt_id');
        }

        if ($status instanceof PutawayTaskStatus) {
            $query->andWhere('t.status = :status')->setParameter('status', $status);
        }

        return $query;
    }
}
