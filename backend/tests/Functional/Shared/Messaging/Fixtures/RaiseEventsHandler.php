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
final readonly class RaiseEventsHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventBus $events,
    ) {
    }

    public function __invoke(RaiseEvents $command): bool
    {
        $tenant = Tenant::register(
            TenantId::fromString($command->tenantId),
            TenantName::fromString('Tenant of a guard test'),
            Language::Spanish,
            new \DateTimeImmutable('2026-10-25T10:00:00+00:00'),
        );
        $this->entityManager->persist($tenant);

        if ($command->publish) {
            $this->events->publish(...$tenant->pullEvents());
        }

        return true;
    }
}
