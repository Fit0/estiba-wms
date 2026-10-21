<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\Messaging\Fixtures;

use App\Identity\Domain\Tenant;
use App\Identity\Domain\TenantName;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Domain\Language;
use App\Shared\Domain\TenantId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class PublishSomethingHappenedHandler
{
    public function __construct(
        private EventBus $events,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(PublishSomethingHappened $command): void
    {
        $this->entityManager->persist(Tenant::register(
            TenantId::fromString($command->tenantId),
            TenantName::fromString('Tenant of a command test'),
            Language::Spanish,
            new \DateTimeImmutable('2026-10-21T10:00:00+00:00'),
        ));

        $this->events->publish(new SomethingHappened($command->policyTenantId, $command->policyFails));

        if ($command->commandFailsAfterPublishing) {
            throw TestRuleBroken::now();
        }
    }
}
