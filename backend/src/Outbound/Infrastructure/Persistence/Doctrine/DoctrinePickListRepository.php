<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Persistence\Doctrine;

use App\Outbound\Domain\PickList;
use App\Outbound\Domain\PickListId;
use App\Outbound\Domain\PickListRepository;
use App\Outbound\Domain\WaveId;
use App\Shared\Domain\TenantId;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Stages the pick list with the unit of work. It does not flush: the command bus does.
 */
final readonly class DoctrinePickListRepository implements PickListRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[\Override]
    public function find(PickListId $id): ?PickList
    {
        return $this->entityManager->find(PickList::class, $id);
    }

    #[\Override]
    public function findOf(TenantId $tenantId, ?WaveId $waveId): array
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(PickList::class, 'p')
            ->where('p.tenantId = :tenant')
            ->setParameter('tenant', $tenantId, 'tenant_id')
            ->orderBy('p.createdAt', 'DESC')
            ->addOrderBy('p.id');

        if ($waveId instanceof WaveId) {
            $query->andWhere('p.waveId = :wave')->setParameter('wave', $waveId, 'wave_id');
        }

        /** @var list<PickList> $found */
        $found = $query->getQuery()->getResult();

        return $found;
    }

    #[\Override]
    public function save(PickList $pickList): void
    {
        $this->entityManager->persist($pickList);
    }
}
