<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\Messaging\Fixtures;

use App\Shared\Application\Bus\EventBus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class PublishSomethingHappenedHandler
{
    public function __construct(private EventBus $events)
    {
    }

    public function __invoke(PublishSomethingHappened $command): void
    {
        $this->events->publish(new SomethingHappened($command->tenantId, $command->policyFails));
    }
}
