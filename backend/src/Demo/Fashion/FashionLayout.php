<?php

declare(strict_types=1);

namespace App\Demo\Fashion;

use App\Warehouse\Domain\LocationCapacity;
use App\Warehouse\Domain\LocationType;

/**
 * The layout of the demo distribution centre of a fashion company, which receives the collection
 * from its factories and ships it to its own stores and to its franchises.
 *
 * Zones follow how the garments are handled, not how they are kept: hanging garments (GOH, "goods
 * on hangers"), folded garments, whole cartons, and shoes with accessories.
 */
final class FashionLayout
{
    public const string WAREHOUSE_CODE = 'ZGZ1';

    public const string WAREHOUSE_NAME = 'Centro logístico Zaragoza';

    /**
     * @return list<DemoZone>
     */
    public function zones(): array
    {
        return [
            new DemoZone('COLG', 'Colgado (GOH)', 1),
            new DemoZone('PLEG', 'Prendas plegadas', 2),
            new DemoZone('RESV', 'Reserva de cajas completas', 3),
            new DemoZone('COMP', 'Calzado y complementos', 4),
            new DemoZone('MUEL', 'Muelles y preparación de pedidos', 5),
            new DemoZone('DEVO', 'Devoluciones de tienda', 6),
        ];
    }

    /**
     * @return list<DemoLocation>
     */
    public function locations(): array
    {
        $locations = [];
        $sequence = 0;

        // Aisle A: pallet racks for whole cartons, 4 modules of 3 levels.
        foreach ($this->range(4) as $module) {
            foreach ($this->range(3) as $level) {
                $locations[] = $this->location('RESV', 'A', $module, $level, LocationType::Reserve, LocationCapacity::of(maxWeightKg: 800, maxPalletPositions: 1), $sequence);
            }
        }

        // Aisle B: shelves of folded garments, where operators pick units. 6 modules of 2 levels.
        foreach ($this->range(6) as $module) {
            foreach ($this->range(2) as $level) {
                $locations[] = $this->location('PLEG', 'B', $module, $level, LocationType::PickFace, LocationCapacity::of(maxWeightKg: 120, maxVolumeLitres: 300), $sequence);
            }
        }

        // Aisle C: rails of hanging garments, 4 modules.
        foreach ($this->range(4) as $module) {
            $locations[] = $this->location('COLG', 'C', $module, 1, LocationType::PickFace, LocationCapacity::of(maxWeightKg: 300), $sequence);
        }

        // Aisle F: shoes and accessories, 3 modules of 2 levels.
        foreach ($this->range(3) as $module) {
            foreach ($this->range(2) as $level) {
                $locations[] = $this->location('COMP', 'F', $module, $level, LocationType::PickFace, LocationCapacity::of(maxWeightKg: 150, maxVolumeLitres: 250), $sequence);
            }
        }

        // Where goods arrive and leave, and where orders are prepared. No limits worth tracking.
        $locations[] = $this->location('MUEL', 'D', 1, 1, LocationType::Dock, LocationCapacity::unlimited(), $sequence);
        $locations[] = $this->location('MUEL', 'D', 2, 1, LocationType::Dock, LocationCapacity::unlimited(), $sequence);
        $locations[] = $this->location('MUEL', 'E', 1, 1, LocationType::Staging, LocationCapacity::unlimited(), $sequence);
        $locations[] = $this->location('MUEL', 'E', 1, 2, LocationType::Staging, LocationCapacity::unlimited(), $sequence);
        $locations[] = $this->location('MUEL', 'P', 1, 1, LocationType::Packing, LocationCapacity::unlimited(), $sequence);
        $locations[] = $this->location('MUEL', 'P', 1, 2, LocationType::Packing, LocationCapacity::unlimited(), $sequence);

        // Returns from stores wait here until someone decides what to do with them.
        $locations[] = $this->location('DEVO', 'Q', 1, 1, LocationType::Quarantine, LocationCapacity::unlimited(), $sequence);
        $locations[] = $this->location('DEVO', 'Q', 1, 2, LocationType::Quarantine, LocationCapacity::unlimited(), $sequence);

        return $locations;
    }

    private function location(string $zone, string $aisle, int $module, int $level, LocationType $type, LocationCapacity $capacity, int &$sequence): DemoLocation
    {
        $sequence += 10;

        return new DemoLocation($zone, \sprintf('%s-%02d-%02d', $aisle, $module, $level), $type, $capacity, $sequence);
    }

    /**
     * @return non-empty-list<int>
     */
    private function range(int $to): array
    {
        return range(1, $to);
    }
}
