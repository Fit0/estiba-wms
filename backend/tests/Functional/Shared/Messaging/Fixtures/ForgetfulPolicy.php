<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\Messaging\Fixtures;

use App\Identity\Domain\Tenant;
use App\Identity\Domain\TenantName;
use App\Shared\Domain\Language;
use App\Shared\Domain\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A policy that registers a tenant, which raises an event, and never publishes it.
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class ForgetfulPolicy
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function __invoke(ForgetfulTrigger $event): void
    {
        $this->entityManager->persist(Tenant::register(
            TenantId::fromString($event->tenantId),
            TenantName::fromString('Tenant of a forgetful policy'),
            Language::Spanish,
            $event->occurredAt(),
        ));
    }
}
