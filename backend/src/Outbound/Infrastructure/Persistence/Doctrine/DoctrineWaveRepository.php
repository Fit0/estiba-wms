<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Persistence\Doctrine;

use App\Outbound\Domain\Wave;
use App\Outbound\Domain\WaveId;
use App\Outbound\Domain\WaveRepository;
use App\Outbound\Domain\WaveStatus;
use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;
use App\Shared\Domain\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Stages the wave with the unit of work. It does not flush: the command bus does.
 */
final readonly class DoctrineWaveRepository implements WaveRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[\Override]
    public function find(WaveId $id): ?Wave
    {
        return $this->entityManager->find(Wave::class, $id);
    }

    #[\Override]
    public function search(TenantId $tenantId, ?WaveStatus $status, PageRequest $page): Page
    {
        $total = $this->filtered($tenantId, $status)
            ->select('COUNT(w.id)')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<Wave> $items */
        $items = $this->filtered($tenantId, $status)
            ->select('w')
            ->orderBy('w.createdAt', 'DESC')
            ->addOrderBy('w.id', 'DESC')
            ->setFirstResult($page->offset())
            ->setMaxResults($page->itemsPerPage)
            ->getQuery()
            ->getResult();

        return new Page($items, (int) $total, $page);
    }

    #[\Override]
    public function save(Wave $wave): void
    {
        $this->entityManager->persist($wave);
    }

    private function filtered(TenantId $tenantId, ?WaveStatus $status): QueryBuilder
    {
        $query = $this->entityManager->createQueryBuilder()
            ->from(Wave::class, 'w')
            ->where('w.tenantId = :tenant')
            ->setParameter('tenant', $tenantId, 'tenant_id');

        if ($status instanceof WaveStatus) {
            $query->andWhere('w.status = :status')->setParameter('status', $status);
        }

        return $query;
    }
}
