<?php

declare(strict_types=1);

namespace App\Inventory\Application;

use App\Catalog\Application\Contract\ProductCatalog;
use App\Catalog\Application\Contract\ProductSnapshot;
use App\Inventory\Application\Contract\StockAvailability;
use App\Inventory\Application\Contract\UnreservableReason;
use App\Inventory\Application\Contract\UnreservableStock;
use App\Inventory\Domain\ProductId;
use App\Inventory\Domain\ReservationReference;
use App\Inventory\Domain\StockItem;
use App\Inventory\Domain\StockItemRepository;
use App\Inventory\Domain\StockReservation;
use App\Inventory\Domain\StockReservationRepository;
use App\Inventory\Domain\StockStatus;
use App\Shared\Domain\TenantId;
use App\Warehouse\Application\Contract\LocationDirectory;
use App\Warehouse\Application\Contract\LocationSnapshot;

/**
 * Answers the {@see StockAvailability} contract by asking, for each stock item of the product, the
 * same questions that {@see LedgerStockReservations} asks to decide what it may promise, and
 * keeping the answer instead of skipping the item (STK-17).
 */
final readonly class LedgerStockAvailability implements StockAvailability
{
    /** How many location codes are given for a reason: enough to find the goods, not a whole warehouse. */
    private const int MAX_LOCATION_CODES = 5;

    /** The location types where stock waits to be picked (STK-06). */
    private const array PICKABLE_TYPES = [LocationSnapshot::TYPE_RESERVE, LocationSnapshot::TYPE_PICK_FACE];

    public function __construct(
        private StockItemRepository $stockItems,
        private StockReservationRepository $reservations,
        private ProductCatalog $catalog,
        private LocationDirectory $locations,
    ) {
    }

    #[\Override]
    public function unreservable(TenantId $tenantId, string $productId, string $warehouseId, string $reference, \DateTimeImmutable $at): array
    {
        $earliestExpiry = $this->earliestExpiry($this->catalog->findById($tenantId, $productId), $at);

        /** @var array<string, int> $units */
        $units = [];
        /** @var array<string, array<string, true>> $codes */
        $codes = [];
        $heldInPickable = 0;
        /** @var array<string, LocationSnapshot|null> $known */
        $known = [];

        foreach ($this->stockItems->findOf($tenantId, ProductId::fromString($productId)) as $stockItem) {
            $locationId = $stockItem->key()->locationId->value;
            $known[$locationId] ??= $this->locations->findById($tenantId, $locationId);
            $location = $known[$locationId];

            $reason = $this->reasonFor($stockItem, $location, $warehouseId, $earliestExpiry);
            if (!$reason instanceof UnreservableReason) {
                $heldInPickable += $stockItem->reserved()->units;

                continue;
            }

            $free = $stockItem->available()->units;
            if ($free > 0) {
                $units[$reason->value] = ($units[$reason->value] ?? 0) + $free;
                $codes[$reason->value][$location instanceof LocationSnapshot ? $location->code : $locationId] = true;
            }
        }

        $others = $heldInPickable - $this->ownUnits($tenantId, $reference);
        if ($others > 0) {
            $units[UnreservableReason::ReservedForOthers->value] = $others;
        }

        return $this->described($units, $codes);
    }

    /**
     * Why the stock item cannot be used, or null when it is where and what an allocation wants. The
     * place comes first: stock at a dock is explained by the dock, whatever its status.
     */
    private function reasonFor(StockItem $stockItem, ?LocationSnapshot $location, string $warehouseId, ?\DateTimeImmutable $earliestExpiry): ?UnreservableReason
    {
        $expiry = $stockItem->key()->expiryDate;

        return match (true) {
            !$location instanceof LocationSnapshot || !$location->active => UnreservableReason::BlockedLocation,
            $location->warehouseId !== $warehouseId => UnreservableReason::OtherWarehouse,
            $location->isDock() => UnreservableReason::AtDock,
            !\in_array($location->type, self::PICKABLE_TYPES, true) => UnreservableReason::NotPickableLocation,
            StockStatus::Available !== $stockItem->key()->status => UnreservableReason::NotAvailable,
            $earliestExpiry instanceof \DateTimeImmutable && $expiry instanceof \DateTimeImmutable && $expiry < $earliestExpiry => UnreservableReason::ShelfLife,
            default => null,
        };
    }

    /**
     * The same limit as the allocation: stock that expires before this day does not keep the shelf life
     * the product promises (STK-12).
     */
    private function earliestExpiry(?ProductSnapshot $product, \DateTimeImmutable $at): ?\DateTimeImmutable
    {
        if (!$product instanceof ProductSnapshot || !$product->trackedByExpiry || null === $product->minShelfLifeAtShipmentDays) {
            return null;
        }

        return $at->setTime(0, 0)->modify(\sprintf('+%d days', $product->minShelfLifeAtShipmentDays));
    }

    private function ownUnits(TenantId $tenantId, string $reference): int
    {
        $own = $this->reservations->findByReference($tenantId, ReservationReference::fromString($reference));

        return $own instanceof StockReservation ? $own->allocated()->units : 0;
    }

    /**
     * @param array<string, int>                 $units
     * @param array<string, array<string, true>> $codes
     *
     * @return list<UnreservableStock>
     */
    private function described(array $units, array $codes): array
    {
        $described = [];
        foreach (UnreservableReason::cases() as $reason) {
            if (isset($units[$reason->value])) {
                $places = array_map(strval(...), array_keys($codes[$reason->value] ?? []));
                sort($places);
                $described[] = new UnreservableStock($reason, $units[$reason->value], \array_slice($places, 0, self::MAX_LOCATION_CODES));
            }
        }

        return $described;
    }
}
