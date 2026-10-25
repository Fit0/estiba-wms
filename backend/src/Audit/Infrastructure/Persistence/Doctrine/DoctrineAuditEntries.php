<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Persistence\Doctrine;

use App\Audit\Domain\AuditEntries;
use App\Audit\Domain\AuditEntry;
use App\Audit\Domain\AuditFilter;
use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;
use App\Shared\Domain\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Stages the entry with the unit of work, to be written with the change it describes. It does not
 * flush: the bus that runs the change does.
 */
final readonly class DoctrineAuditEntries implements AuditEntries
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[\Override]
    public function add(AuditEntry $entry): void
    {
        $this->entityManager->persist($entry);
    }

    #[\Override]
    public function search(TenantId $tenantId, AuditFilter $filter, PageRequest $page): Page
    {
        $total = $this->filtered($tenantId, $filter)
            ->select('COUNT(e.id)')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<AuditEntry> $items */
        $items = $this->filtered($tenantId, $filter)
            ->select('e')
            ->orderBy('e.occurredAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setFirstResult($page->offset())
            ->setMaxResults($page->itemsPerPage)
            ->getQuery()
            ->getResult();

        return new Page($items, (int) $total, $page);
    }

    private function filtered(TenantId $tenantId, AuditFilter $filter): QueryBuilder
    {
        $query = $this->entityManager->createQueryBuilder()
            ->from(AuditEntry::class, 'e')
            ->where('e.tenantId = :tenant')
            ->setParameter('tenant', $tenantId, 'tenant_id');

        if (null !== $filter->eventType) {
            $query->andWhere('e.eventType = :eventType')->setParameter('eventType', $filter->eventType);
        }
        if (null !== $filter->actorId) {
            $query->andWhere('e.actorId = :actorId')->setParameter('actorId', $filter->actorId);
        }
        if (null !== $filter->subjectId) {
            $query->andWhere('e.subjectId = :subjectId')->setParameter('subjectId', $filter->subjectId);
        }
        if ($filter->from instanceof \DateTimeImmutable) {
            $query->andWhere('e.occurredAt >= :from')->setParameter('from', $filter->from, 'datetime_immutable');
        }
        if ($filter->until instanceof \DateTimeImmutable) {
            $query->andWhere('e.occurredAt < :until')->setParameter('until', $filter->until, 'datetime_immutable');
        }

        return $query;
    }
}
