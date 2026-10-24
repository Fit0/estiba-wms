<?php

declare(strict_types=1);

namespace App\Outbound\Application\StartPickList;

use App\Outbound\Application\PickListLookup;
use App\Outbound\Domain\OperatorId;
use App\Outbound\Domain\PickList;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Clock;
use App\Shared\Application\CurrentUser;

final readonly class StartPickListHandler implements CommandHandler
{
    public function __construct(
        private PickListLookup $lookup,
        private CurrentUser $user,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(StartPickList $command): PickList
    {
        $pickList = $this->lookup->get($command->pickListId);
        $pickList->start(OperatorId::fromString($this->user->id()), $this->clock->now());
        $this->events->publish(...$pickList->pullEvents());

        return $pickList;
    }
}
