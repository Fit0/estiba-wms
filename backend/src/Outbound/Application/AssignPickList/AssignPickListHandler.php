<?php

declare(strict_types=1);

namespace App\Outbound\Application\AssignPickList;

use App\Outbound\Application\PickListLookup;
use App\Outbound\Domain\OperatorId;
use App\Outbound\Domain\PickList;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;

final readonly class AssignPickListHandler implements CommandHandler
{
    public function __construct(
        private PickListLookup $lookup,
        private EventBus $events,
    ) {
    }

    public function __invoke(AssignPickList $command): PickList
    {
        $pickList = $this->lookup->get($command->pickListId);
        $pickList->assign(OperatorId::fromString($command->operatorId));

        $this->events->publish(...$pickList->pullEvents());

        return $pickList;
    }
}
