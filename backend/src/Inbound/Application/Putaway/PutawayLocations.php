<?php

declare(strict_types=1);

namespace App\Inbound\Application\Putaway;

use App\Catalog\Application\Contract\ProductCatalog;
use App\Catalog\Application\Contract\ProductSnapshot;
use App\Inbound\Domain\Exception\InvalidPutawayDestination;
use App\Inbound\Domain\LocationId;
use App\Inbound\Domain\Putaway\PutawayCandidate;
use App\Inbound\Domain\Putaway\PutawayDestination;
use App\Inbound\Domain\ReceivedGoods;
use App\Inventory\Application\Contract\StockAtLocation;
use App\Inventory\Application\Contract\StockLevels;
use App\Shared\Domain\TenantId;
use App\Warehouse\Application\Contract\LocationDirectory;
use App\Warehouse\Application\Contract\LocationSnapshot;

/**
 * Asks Warehouse, Inventory and Catalog what putaway needs to know about locations: which ones
 * could take the goods, whether they are empty, and whether the units fit (INB-11).
 *
 * The room of a location is worked out from the weight and the volume of what it holds and of the
 * units that arrive. A location without limits always has room. Pallet positions are not counted:
 * stock is not tracked by pallet (DD-10).
 */
final readonly class PutawayLocations
{
    private const int GRAMS_PER_KG = 1000;

    private const int CUBIC_MM_PER_LITRE = 1_000_000;

    public function __construct(
        private LocationDirectory $locations,
        private StockLevels $stock,
        private ProductCatalog $catalog,
    ) {
    }

    /**
     * The locations of the dock's warehouse that could take the goods, in the order of the picking
     * route. Locations in `$taken` were just suggested for other goods and count as not empty.
     *
     * @param list<string> $taken
     *
     * @return list<PutawayCandidate>
     */
    public function candidatesFor(TenantId $tenantId, LocationId $dockId, ProductSnapshot $product, ReceivedGoods $goods, array $taken): array
    {
        $dock = $this->locations->findById($tenantId, $dockId->value);
        if (!$dock instanceof LocationSnapshot) {
            return [];
        }

        $suitable = array_values(array_filter(
            $this->locations->activeLocationsOf($tenantId, $dock->warehouseId, $this->typesFor($goods)),
            static fn (LocationSnapshot $location): bool => $location->storageClass === $product->storageClass,
        ));
        $held = $this->heldIn($tenantId, $suitable);

        return array_map(fn (LocationSnapshot $location): PutawayCandidate => new PutawayCandidate(
            LocationId::fromString($location->id),
            $location->code,
            $location->families,
            !isset($held[$location->id]) && !\in_array($location->id, $taken, true),
            $this->hasRoom($tenantId, $location, $product, $goods->units, $held[$location->id] ?? []),
        ), $suitable);
    }

    /**
     * What the warehouse says, right now, about the location an operator chose.
     *
     * @throws InvalidPutawayDestination when the tenant has no such location
     */
    public function destination(TenantId $tenantId, string $locationId, ProductSnapshot $product, ReceivedGoods $goods): PutawayDestination
    {
        $location = $this->locations->findById($tenantId, $locationId);
        if (!$location instanceof LocationSnapshot) {
            throw InvalidPutawayDestination::unknown($locationId);
        }

        return new PutawayDestination(
            LocationId::fromString($location->id),
            $location->active,
            \in_array($location->type, $this->typesFor($goods), true),
            $location->storageClass === $product->storageClass,
            $this->hasRoom($tenantId, $location, $product, $goods->units, $this->heldIn($tenantId, [$location])[$location->id] ?? []),
        );
    }

    /**
     * Damaged goods wait in quarantine (INB-10). The rest goes to storage: reserve or pick faces.
     *
     * @return list<string>
     */
    private function typesFor(ReceivedGoods $goods): array
    {
        return $goods->quarantine
            ? [LocationSnapshot::TYPE_QUARANTINE]
            : [LocationSnapshot::TYPE_RESERVE, LocationSnapshot::TYPE_PICK_FACE];
    }

    /**
     * @param list<LocationSnapshot> $locations
     *
     * @return array<string, list<StockAtLocation>> what each location holds, by location identifier
     */
    private function heldIn(TenantId $tenantId, array $locations): array
    {
        $held = [];
        foreach ($this->stock->at($tenantId, array_map(static fn (LocationSnapshot $location): string => $location->id, $locations)) as $stock) {
            $held[$stock->locationId][] = $stock;
        }

        return $held;
    }

    /**
     * @param list<StockAtLocation> $held
     */
    private function hasRoom(TenantId $tenantId, LocationSnapshot $location, ProductSnapshot $product, int $units, array $held): bool
    {
        $grams = $units * $product->weightGrams;
        $cubicMm = $units * $this->volumeOf($product);

        foreach ($held as $stock) {
            $other = $this->catalog->findById($tenantId, $stock->productId);
            if ($other instanceof ProductSnapshot) {
                $grams += $stock->units * $other->weightGrams;
                $cubicMm += $stock->units * $this->volumeOf($other);
            }
        }

        return (null === $location->maxWeightKg || $grams <= $location->maxWeightKg * self::GRAMS_PER_KG)
            && (null === $location->maxVolumeLitres || $cubicMm <= $location->maxVolumeLitres * self::CUBIC_MM_PER_LITRE);
    }

    private function volumeOf(ProductSnapshot $product): int
    {
        return $product->lengthMm * $product->widthMm * $product->heightMm;
    }
}
