<?php

declare(strict_types=1);

namespace App\Inbound\Application\ConfirmPutaway;

use App\Catalog\Application\Contract\ProductCatalog;
use App\Inbound\Application\Putaway\PutawayLocations;
use App\Inbound\Application\PutawayTaskLookup;
use App\Inbound\Domain\Exception\ProductNotReceivable;
use App\Inbound\Domain\OperatorId;
use App\Inbound\Domain\OverrideReason;
use App\Inbound\Domain\PutawayTask;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Clock;
use App\Shared\Application\CurrentUser;

/**
 * Checks the location in the same request (INB-11), confirms the task, and publishes
 * `PutawayConfirmed`, whose policy moves the stock in the same transaction.
 */
final readonly class ConfirmPutawayHandler implements CommandHandler
{
    public function __construct(
        private PutawayTaskLookup $lookup,
        private PutawayLocations $locations,
        private ProductCatalog $catalog,
        private CurrentUser $user,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(ConfirmPutaway $command): PutawayTask
    {
        $task = $this->lookup->get($command->taskId);
        $goods = $task->goods();
        $product = $this->catalog->findById($task->tenantId(), $goods->productId->value)
            ?? throw ProductNotReceivable::unknown($goods->productId->value);

        $task->confirm(
            $this->locations->destination($task->tenantId(), $command->locationId, $product, $goods),
            null === $command->reason ? null : OverrideReason::fromString($command->reason),
            OperatorId::fromString($this->user->id()),
            $this->clock->now(),
        );
        $this->events->publish(...$task->pullEvents());

        return $task;
    }
}
