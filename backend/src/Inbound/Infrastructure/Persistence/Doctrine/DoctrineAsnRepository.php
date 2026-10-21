<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Persistence\Doctrine;

use App\Inbound\Domain\Asn;
use App\Inbound\Domain\AsnId;
use App\Inbound\Domain\AsnReference;
use App\Inbound\Domain\AsnRepository;
use App\Inbound\Domain\AsnStatus;
use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;
use App\Shared\Domain\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Stages the ASN with the unit of work. It does not flush: the caller that owns the
 * transaction does.
 */
final readonly class DoctrineAsnRepository implements AsnRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[\Override]
    public function find(AsnId $id): ?Asn
    {
        return $this->entityManager->find(Asn::class, $id);
    }

    #[\Override]
    public function search(TenantId $tenantId, ?AsnStatus $status, PageRequest $page): Page
    {
        $total = $this->filtered($tenantId, $status)
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<Asn> $items */
        $items = $this->filtered($tenantId, $status)
            ->select('a')
            ->orderBy('a.expectedDate', 'DESC')
            ->addOrderBy('a.reference')
            ->setFirstResult($page->offset())
            ->setMaxResults($page->itemsPerPage)
            ->getQuery()
            ->getResult();

        return new Page($items, (int) $total, $page);
    }

    #[\Override]
    public function save(Asn $asn): void
    {
        $this->entityManager->persist($asn);
    }

    #[\Override]
    public function isReferenceTaken(TenantId $tenantId, AsnReference $reference): bool
    {
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(Asn::class, 'a')
            ->where('a.tenantId = :tenant')
            ->andWhere('a.reference = :reference')
            ->setParameter('tenant', $tenantId, 'tenant_id')
            ->setParameter('reference', $reference, 'asn_reference')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    private function filtered(TenantId $tenantId, ?AsnStatus $status): QueryBuilder
    {
        $query = $this->entityManager->createQueryBuilder()
            ->from(Asn::class, 'a')
            ->where('a.tenantId = :tenant')
            ->setParameter('tenant', $tenantId, 'tenant_id');

        if ($status instanceof AsnStatus) {
            $query->andWhere('a.status = :status')->setParameter('status', $status);
        }

        return $query;
    }
}
