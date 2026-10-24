<?php

declare(strict_types=1);

namespace App\Outbound\Application\ConfirmPickLine;

use App\Catalog\Application\Contract\ProductCatalog;
use App\Catalog\Application\Contract\ProductSnapshot;
use App\Inventory\Application\Contract\StockPick;
use App\Inventory\Application\Contract\StockReservations;
use App\Outbound\Application\OrderLookup;
use App\Outbound\Application\PickListLookup;
use App\Outbound\Application\StagingLocations;
use App\Outbound\Application\WaveLookup;
use App\Outbound\Domain\OperatorId;
use App\Outbound\Domain\OrderLine;
use App\Outbound\Domain\PickList;
use App\Outbound\Domain\PickListRepository;
use App\Outbound\Domain\PickListStatus;
use App\Outbound\Domain\ProductId;
use App\Outbound\Domain\ScannedPick;
use App\Outbound\Domain\ShortPickReason;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Clock;
use App\Shared\Application\CurrentTenant;
use App\Shared\Application\CurrentUser;
use App\Shared\Domain\DomainError;
use App\Shared\Domain\DomainEvent;

/**
 * Confirming a stop is one transaction: the stop on the list, the units in the stock, the line of the
 * order and, when this was the last stop, the list and the wave.
 *
 * The picked units go to a staging location at once and stay promised to the order, so the shelf shows
 * what is on it and nobody plans to pick units that are already on the cart (STK-13). The units not found
 * are given up and become part of the shortage of the order line. The event board shows the move to staging
 * as a policy of `PickListCompleted`; it is done per stop for the reason above (ADR 0016).
 */
final readonly class ConfirmPickLineHandler implements CommandHandler
{
    public function __construct(
        private PickListLookup $pickLists,
        private PickListRepository $pickListRepository,
        private OrderLookup $orders,
        private WaveLookup $waves,
        private ProductCatalog $catalog,
        private StockReservations $reservations,
        private StagingLocations $staging,
        private CurrentTenant $tenant,
        private CurrentUser $user,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(ConfirmPickLine $command): PickList
    {
        $pickList = $this->pickLists->get($command->pickListId);
        $operator = OperatorId::fromString($this->user->id());
        $now = $this->clock->now();

        $line = $pickList->confirmLine(
            $command->position,
            new ScannedPick($command->location, $this->productScanned($command->gtin), $command->lot),
            $command->units,
            null === $command->reason ? null : ShortPickReason::fromString($command->reason),
            $operator,
            $now,
        );

        $order = $this->orders->get($line->orderId->value);
        $this->reservations->pick(new StockPick(
            $this->tenant->id(),
            $this->reservationOf($order->lines(), $line->orderLineNumber),
            $line->stockItemId,
            $line->quantityPicked,
            $line->missingUnits(),
            $this->staging->of($order->warehouseId()),
            \sprintf('pick:%s:%d', $pickList->id()->value, $line->position),
            $operator->value,
            $now,
        ));
        $order->recordPick($line->orderLineNumber, $line->quantityPicked, $line->missingUnits(), $now);

        $this->events->publish(...$pickList->pullEvents(), ...$order->pullEvents(), ...$this->completeWave($pickList, $now));

        return $pickList;
    }

    /**
     * @return list<DomainEvent>
     */
    private function completeWave(PickList $pickList, \DateTimeImmutable $now): array
    {
        if (PickListStatus::Completed !== $pickList->status()) {
            return [];
        }

        $wave = $this->waves->get($pickList->waveId()->value);
        $wave->completeWhenDone(
            array_map(static fn (PickList $other): PickListStatus => $other->status(), $this->pickListRepository->findOf($this->tenant->id(), $wave->id())),
            $now,
        );

        return $wave->pullEvents();
    }

    /**
     * What the barcode belongs to, or null when it is not a barcode of the tenant. Scanning a label that
     * is not a product is a mistake on the floor, not a broken request.
     */
    private function productScanned(string $gtin): ?ProductId
    {
        try {
            $product = $this->catalog->findByGtin($this->tenant->id(), $gtin);
        } catch (DomainError) {
            return null;
        }

        return $product instanceof ProductSnapshot ? ProductId::fromString($product->id) : null;
    }

    /**
     * @param non-empty-list<OrderLine> $lines
     */
    private function reservationOf(array $lines, int $lineNumber): string
    {
        foreach ($lines as $line) {
            if ($line->lineNumber === $lineNumber && null !== $line->reservationId) {
                return $line->reservationId->value;
            }
        }

        throw new \LogicException(\sprintf('Line %d of the order has no reservation, but its stock was put on a pick list.', $lineNumber));
    }
}
