<?php

declare(strict_types=1);

namespace App\Inventory\Application;

use App\Catalog\Application\Contract\ProductCatalog;
use App\Catalog\Application\Contract\ProductSnapshot;
use App\Inventory\Application\Contract\AllocationRequest;
use App\Inventory\Application\Contract\AllocationResult;
use App\Inventory\Application\Contract\ReservedStock;
use App\Inventory\Application\Contract\StockPick;
use App\Inventory\Application\Contract\StockReservations;
use App\Inventory\Domain\AllocationPlanner;
use App\Inventory\Domain\AllocationStrategy;
use App\Inventory\Domain\AuthorId;
use App\Inventory\Domain\Exception\ProductNotAllocatable;
use App\Inventory\Domain\Exception\ReservationNotActive;
use App\Inventory\Domain\Exception\ReservationNotFound;
use App\Inventory\Domain\Exception\ReservationRequestChanged;
use App\Inventory\Domain\Exception\StockItemNotFound;
use App\Inventory\Domain\LocationId;
use App\Inventory\Domain\MovementReference;
use App\Inventory\Domain\ProductId;
use App\Inventory\Domain\Quantity;
use App\Inventory\Domain\ReservationId;
use App\Inventory\Domain\ReservationReference;
use App\Inventory\Domain\ReservationStatus;
use App\Inventory\Domain\StockItem;
use App\Inventory\Domain\StockItemId;
use App\Inventory\Domain\StockItemRepository;
use App\Inventory\Domain\StockKey;
use App\Inventory\Domain\StockMovementId;
use App\Inventory\Domain\StockReservation;
use App\Inventory\Domain\StockReservationRepository;
use App\Inventory\Domain\StockStatus;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\IdGenerator;
use App\Shared\Domain\TenantId;
use App\Warehouse\Application\Contract\LocationDirectory;
use App\Warehouse\Application\Contract\LocationSnapshot;

/**
 * Answers the {@see StockReservations} contract on the stock items and the reservations.
 *
 * Only stock in a reserve or pick face location of the warehouse can be promised (STK-06):
 * stock at a dock has not been put away, and the rest is not meant to be sold. A blocked location
 * is never used (WH-06).
 *
 * The write is in the caller's transaction. The stock items carry a version, so two requests that
 * reserve from the same one cannot both succeed (STK-02): the second is refused when it saves.
 */
final readonly class LedgerStockReservations implements StockReservations
{
    /** The location types where stock waits to be picked. */
    private const array PICKABLE_TYPES = [LocationSnapshot::TYPE_RESERVE, LocationSnapshot::TYPE_PICK_FACE];

    public function __construct(
        private StockItemRepository $stockItems,
        private StockReservationRepository $reservations,
        private ProductCatalog $catalog,
        private LocationDirectory $locations,
        private AllocationPlanner $planner,
        private IdGenerator $ids,
        private EventBus $events,
    ) {
    }

    #[\Override]
    public function allocate(AllocationRequest $request): AllocationResult
    {
        $product = $this->catalog->findById($request->tenantId, $request->productId) ?? throw ProductNotAllocatable::unknown($request->productId);
        if (!$product->availableForOperations) {
            throw ProductNotAllocatable::unavailable($request->productId);
        }

        $reservation = $this->reservationFor($request, $product);

        if (!$reservation->missing()->isZero()) {
            $this->cover($reservation, $request, $product);
        }

        $this->reservations->save($reservation);

        return new AllocationResult($reservation->id()->value, $reservation->requested()->units, $reservation->allocated()->units);
    }

    #[\Override]
    public function release(TenantId $tenantId, string $reservationId): void
    {
        $reservation = $this->reservationOf($tenantId, $reservationId);

        // Nothing comes back the second time (STK-14), so the stock items are only freed once.
        foreach ($reservation->release() as $allocation) {
            $stockItem = $this->stockItems->find($allocation->stockItemId) ?? throw StockItemNotFound::forId($allocation->stockItemId->value);
            $stockItem->unreserve($allocation->quantity);
            $this->stockItems->save($stockItem);
        }

        $this->reservations->save($reservation);
    }

    #[\Override]
    public function pick(StockPick $pick): void
    {
        $reservation = $this->reservationOf($pick->tenantId, $pick->reservationId);
        $source = $this->stockItems->find(StockItemId::fromString($pick->stockItemId));
        if (!$source instanceof StockItem || !$source->tenantId()->equals($pick->tenantId)) {
            throw StockItemNotFound::forId($pick->stockItemId);
        }

        $units = Quantity::of($pick->units);
        $short = Quantity::of($pick->shortUnits);
        $destination = $units->isZero() ? null : $this->stockItemFor($pick->tenantId, new StockKey(
            $source->key()->productId,
            LocationId::fromString($pick->toLocationId),
            $source->key()->lot,
            $source->key()->expiryDate,
            StockStatus::Available,
        ));

        // The reservation checks that it holds that much in the stock item before anything changes.
        $reservation->shift($source->id(), $destination?->id(), $units, $short);

        $source->unreserve($units->plus($short));
        if ($destination instanceof StockItem) {
            $source->transferTo(
                $destination,
                $units,
                StockMovementId::fromString($this->ids->next()),
                StockMovementId::fromString($this->ids->next()),
                MovementReference::fromString($pick->reference),
                AuthorId::fromString($pick->authorId),
                $pick->at,
            );
            // The units are still promised to the demand: now they wait where they were taken.
            $destination->reserve($units);
            $this->stockItems->save($destination);
        }

        $this->stockItems->save($source);
        $this->events->publish(...$source->pullEvents(), ...($destination instanceof StockItem ? $destination->pullEvents() : []));
        $this->reservations->save($reservation);
    }

    #[\Override]
    public function stockOf(TenantId $tenantId, string $reservationId): array
    {
        $reservation = $this->reservationOf($tenantId, $reservationId);

        $held = [];
        foreach ($reservation->allocations() as $allocation) {
            $stockItem = $this->stockItems->find($allocation->stockItemId) ?? throw StockItemNotFound::forId($allocation->stockItemId->value);
            $key = $stockItem->key();

            $held[] = new ReservedStock($stockItem->id()->value, $key->locationId->value, $key->lot?->value, $key->expiryDate, $allocation->quantity->units);
        }

        return $held;
    }

    /**
     * @throws ReservationNotFound when the tenant has no such reservation
     */
    private function reservationOf(TenantId $tenantId, string $reservationId): StockReservation
    {
        $reservation = $this->reservations->find(ReservationId::fromString($reservationId));
        if (!$reservation instanceof StockReservation || !$reservation->tenantId()->equals($tenantId)) {
            throw ReservationNotFound::withId($reservationId);
        }

        return $reservation;
    }

    /**
     * The stock item of the key, or a new one when nothing has been put there yet.
     */
    private function stockItemFor(TenantId $tenantId, StockKey $key): StockItem
    {
        return $this->stockItems->findByKey($tenantId, $key)
            ?? StockItem::open(StockItemId::fromString($this->ids->next()), $tenantId, $key);
    }

    private function reservationFor(AllocationRequest $request, ProductSnapshot $product): StockReservation
    {
        $reference = ReservationReference::fromString($request->reference);
        $existing = $this->reservations->findByReference($request->tenantId, $reference);

        if (!$existing instanceof StockReservation) {
            return StockReservation::open(
                ReservationId::fromString($this->ids->next()),
                $request->tenantId,
                $reference,
                ProductId::fromString($request->productId),
                Quantity::of($request->units),
                $product->trackedByExpiry ? AllocationStrategy::Fefo : AllocationStrategy::Fifo,
                $request->at,
            );
        }

        if (ReservationStatus::Active !== $existing->status()) {
            throw ReservationNotActive::forId($existing->id());
        }
        if ($existing->requested()->units !== $request->units || $existing->productId()->value !== $request->productId) {
            throw ReservationRequestChanged::forReference($reference->value, $existing->requested()->units, $request->units);
        }

        return $existing;
    }

    private function cover(StockReservation $reservation, AllocationRequest $request, ProductSnapshot $product): void
    {
        $candidates = $this->pickableStock($request);
        $plan = $this->planner->plan($candidates, $reservation->missing(), $reservation->strategy(), $this->earliestExpiry($request, $product));

        $byId = [];
        foreach ($candidates as $stockItem) {
            $byId[$stockItem->id()->value] = $stockItem;
        }

        foreach ($plan as $allocation) {
            $stockItem = $byId[$allocation->stockItemId->value];
            $stockItem->reserve($allocation->quantity);
            $this->stockItems->save($stockItem);
        }

        $reservation->allocate($plan);
    }

    /**
     * The stock of the product that waits in an active location where it can be picked, in the
     * warehouse the demand is served from.
     *
     * @return list<StockItem>
     */
    private function pickableStock(AllocationRequest $request): array
    {
        $usable = [];
        $pickable = [];

        foreach ($this->stockItems->findReservable($request->tenantId, ProductId::fromString($request->productId)) as $stockItem) {
            $locationId = $stockItem->key()->locationId->value;

            if (!isset($usable[$locationId])) {
                $location = $this->locations->findById($request->tenantId, $locationId);
                $usable[$locationId] = $location instanceof LocationSnapshot
                    && $location->active
                    && $location->warehouseId === $request->warehouseId
                    && \in_array($location->type, self::PICKABLE_TYPES, true);
            }

            if ($usable[$locationId]) {
                $pickable[] = $stockItem;
            }
        }

        return $pickable;
    }

    /**
     * Stock that expires before this day would reach the customer without the shelf life the
     * product promises (STK-12). Products with no such promise accept any expiry date.
     */
    private function earliestExpiry(AllocationRequest $request, ProductSnapshot $product): ?\DateTimeImmutable
    {
        if (!$product->trackedByExpiry || null === $product->minShelfLifeAtShipmentDays) {
            return null;
        }

        return $request->at->setTime(0, 0)->modify(\sprintf('+%d days', $product->minShelfLifeAtShipmentDays));
    }
}
