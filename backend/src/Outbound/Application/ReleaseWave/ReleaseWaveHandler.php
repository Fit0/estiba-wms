<?php

declare(strict_types=1);

namespace App\Outbound\Application\ReleaseWave;

use App\Outbound\Application\OrderLookup;
use App\Outbound\Application\PickRequests;
use App\Outbound\Application\WaveLookup;
use App\Outbound\Domain\Order;
use App\Outbound\Domain\OrderId;
use App\Outbound\Domain\PickList;
use App\Outbound\Domain\PickListId;
use App\Outbound\Domain\PickListRepository;
use App\Outbound\Domain\PickListSplitter;
use App\Outbound\Domain\SerpentineRoute;
use App\Outbound\Domain\Wave;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Clock;
use App\Shared\Application\CurrentTenant;
use App\Shared\Application\IdGenerator;

/**
 * Releasing builds the pick lists in the same transaction, so a wave is never on the floor without them.
 * The units to pick are the ones reserved for its orders, ordered as a serpentine walk and shared among
 * the operators asked for (OUT-09). The event board shows this as a policy of `WaveReleased`; it is
 * done here because the supervisor needs the lists in the answer.
 */
final readonly class ReleaseWaveHandler implements CommandHandler
{
    public function __construct(
        private WaveLookup $waves,
        private OrderLookup $orders,
        private PickRequests $requests,
        private SerpentineRoute $route,
        private PickListSplitter $splitter,
        private PickListRepository $pickLists,
        private CurrentTenant $tenant,
        private EventBus $events,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    public function __invoke(ReleaseWave $command): Wave
    {
        $wave = $this->waves->get($command->waveId);
        $wave->assertReleasable();

        $orders = array_map(fn (OrderId $id): Order => $this->orders->get($id->value), $wave->orderIds());
        $now = $this->clock->now();

        $ids = [];
        foreach ($this->splitter->split($this->route->order($this->requests->of($orders)), $command->pickLists) as $stretch) {
            $pickList = PickList::generate(PickListId::fromString($this->ids->next()), $this->tenant->id(), $wave->id(), $stretch, $now);

            $this->pickLists->save($pickList);
            $this->events->publish(...$pickList->pullEvents());
            $ids[] = $pickList->id();
        }

        $wave->release($ids, $now);
        $this->events->publish(...$wave->pullEvents());

        return $wave;
    }
}
