<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine;

use App\Identity\Domain\Tenant;
use App\Identity\Domain\TenantRepository;
use App\Shared\Domain\TenantId;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Stages the tenant with the unit of work. It does not flush: the caller that owns the
 * transaction does.
 */
final readonly class DoctrineTenantRepository implements TenantRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[\Override]
    public function find(TenantId $id): ?Tenant
    {
        return $this->entityManager->find(Tenant::class, $id);
    }

    #[\Override]
    public function save(Tenant $tenant): void
    {
        $this->entityManager->persist($tenant);
    }
}
