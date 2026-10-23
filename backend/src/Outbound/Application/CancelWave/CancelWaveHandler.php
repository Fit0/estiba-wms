<?php

declare(strict_types=1);

namespace App\Outbound\Application\CancelWave;

use App\Outbound\Application\OrderLookup;
use App\Outbound\Application\WaveLookup;
use App\Outbound\Domain\PickListRepository;
use App\Outbound\Domain\Wave;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Clock;
use App\Shared\Application\CurrentTenant;

/**
 * The orders of the wave are free again and its pick lists are cancelled, in the same transaction. What
 * was reserved for the orders stays reserved: they are still allocated, and can join another wave.
 */
final readonly class CancelWaveHandler implements CommandHandler
{
    public function __construct(
        private WaveLookup $waves,
        private OrderLookup $orders,
        private PickListRepository $pickLists,
        private CurrentTenant $tenant,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(CancelWave $command): Wave
    {
        $wave = $this->waves->get($command->waveId);

        foreach ($wave->cancel($this->clock->now()) as $orderId) {
            $this->orders->get($orderId->value)->leaveWave();
        }

        foreach ($this->pickLists->findOf($this->tenant->id(), $wave->id()) as $pickList) {
            $pickList->cancel();
        }

        $this->events->publish(...$wave->pullEvents());

        return $wave;
    }
}
