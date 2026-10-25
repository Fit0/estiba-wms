<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\Messaging\Fixtures;

use App\Identity\Domain\Tenant;
use App\Identity\Domain\TenantName;
use App\Shared\Domain\Language;
use App\Shared\Domain\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'event.bus')]
final readonly class SaveTenantWhenSomethingHappened
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function __invoke(SomethingHappened $event): void
    {
        $tenant = Tenant::register(
            TenantId::fromString($event->tenantId),
            TenantName::fromString('Tenant of a policy test'),
            Language::Spanish,
            $event->occurredAt(),
        );
        // The tenant is only something to write: what this fixture tests is the bus, not its events.
        $tenant->pullEvents();
        $this->entityManager->persist($tenant);

        if ($event->policyFails) {
            $this->entityManager->flush();

            throw TestRuleBroken::now();
        }
    }
}
