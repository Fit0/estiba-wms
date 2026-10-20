<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\Messaging\Fixtures;

use App\Identity\Domain\Tenant;
use App\Identity\Domain\TenantName;
use App\Shared\Domain\Language;
use App\Shared\Domain\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class SaveTenantHandler
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return bool whether a transaction was open while the handler ran
     */
    public function __invoke(SaveTenant $command): bool
    {
        $this->entityManager->persist(Tenant::register(
            TenantId::fromString($command->tenantId),
            TenantName::fromString('Tenant of a bus test'),
            Language::Spanish,
            new \DateTimeImmutable('2026-10-21T10:00:00+00:00'),
        ));

        if ($command->failAfterFlushing) {
            $this->entityManager->flush();

            throw new \RuntimeException('Something broke after the write.');
        }

        if ($command->failWithADomainError) {
            throw TestRuleBroken::now();
        }

        return $this->entityManager->getConnection()->isTransactionActive();
    }
}
