<?php

declare(strict_types=1);

namespace App\Outbound\Application\CreateWave;

use App\Outbound\Application\OrderLookup;
use App\Outbound\Domain\Wave;
use App\Outbound\Domain\WaveId;
use App\Outbound\Domain\WaveRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Clock;
use App\Shared\Application\CurrentTenant;
use App\Shared\Application\IdGenerator;

/**
 * The wave is planned first, which checks the orders against each other: the same warehouse and no
 * repeats. Then each order decides whether it may join, which is what protects OUT-05 and OUT-06.
 * Everything happens in one transaction, so an order refused by the last of them leaves none in a wave.
 */
final readonly class CreateWaveHandler implements CommandHandler
{
    public function __construct(
        private OrderLookup $orders,
        private WaveRepository $waves,
        private CurrentTenant $tenant,
        private EventBus $events,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    public function __invoke(CreateWave $command): Wave
    {
        $orders = array_map($this->orders->get(...), $command->orderIds);

        $wave = Wave::plan(WaveId::fromString($this->ids->next()), $this->tenant->id(), $orders, $this->clock->now());
        foreach ($orders as $order) {
            $order->joinWave($wave->id());
        }

        $this->waves->save($wave);
        $this->events->publish(...$wave->pullEvents());

        return $wave;
    }
}
