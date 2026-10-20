<?php

declare(strict_types=1);

namespace App\Tests\Unit\Demo\Fashion;

use App\Demo\Fashion\DemoLocation;
use App\Demo\Fashion\DemoZone;
use App\Demo\Fashion\FashionLayout;
use App\Warehouse\Domain\LocationCode;
use App\Warehouse\Domain\LocationType;
use App\Warehouse\Domain\ZoneCode;
use PHPUnit\Framework\TestCase;

final class FashionLayoutTest extends TestCase
{
    private FashionLayout $layout;

    #[\Override]
    protected function setUp(): void
    {
        $this->layout = new FashionLayout();
    }

    public function testHasTheZonesOfAGarmentFlow(): void
    {
        $codes = array_map(static fn (DemoZone $zone): string => ZoneCode::fromString($zone->code)->value, $this->layout->zones());

        self::assertSame(['COLG', 'PLEG', 'RESV', 'COMP', 'MUEL', 'DEVO'], $codes);
    }

    public function testEveryLocationCodeFollowsAisleModuleLevelAndIsUnique(): void
    {
        $codes = array_map(static fn (DemoLocation $location): string => LocationCode::fromString($location->code)->value, $this->layout->locations());

        self::assertCount(42, $codes);
        self::assertCount(\count($codes), array_unique($codes));
    }

    public function testEveryLocationBelongsToAZoneOfTheLayout(): void
    {
        $zones = array_map(static fn (DemoZone $zone): string => $zone->code, $this->layout->zones());

        foreach ($this->layout->locations() as $location) {
            self::assertContains($location->zone, $zones, $location->code);
        }
    }

    public function testTheWalkingOrderOfEveryLocationIsDifferent(): void
    {
        $sequences = array_map(static fn (DemoLocation $location): int => $location->pickSequence, $this->layout->locations());

        self::assertCount(\count($sequences), array_unique($sequences));
    }

    public function testHasWhereGoodsArriveAndLeaveAndWhereReturnsWait(): void
    {
        $types = array_map(static fn (DemoLocation $location): LocationType => $location->type, $this->layout->locations());

        foreach ([LocationType::Dock, LocationType::Staging, LocationType::Packing, LocationType::Quarantine, LocationType::Reserve, LocationType::PickFace] as $type) {
            self::assertContains($type, $types, $type->value);
        }
    }
}
