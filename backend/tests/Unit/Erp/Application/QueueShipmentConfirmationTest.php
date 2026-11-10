<?php

declare(strict_types=1);

namespace App\Tests\Unit\Erp\Application;

use App\Catalog\Application\Contract\ProductCatalog;
use App\Catalog\Application\Contract\ProductSnapshot;
use App\Erp\Application\QueueShipmentConfirmation;
use App\Outbound\Application\Contract\ShipmentLeft;
use App\Outbound\Application\Contract\ShippedUnits;
use App\Shared\Domain\StorageClass;
use App\Shared\Domain\TenantId;
use App\Shared\Domain\TurnoverClass;
use App\Tests\Support\InMemoryImportedOrders;
use App\Tests\Support\InMemoryShipmentOutbox;
use App\Tests\Unit\Support\Ids;
use PHPUnit\Framework\TestCase;

final class QueueShipmentConfirmationTest extends TestCase
{
    private TenantId $tenant;

    private string $productId;

    private InMemoryImportedOrders $imported;

    private InMemoryShipmentOutbox $outbox;

    #[\Override]
    protected function setUp(): void
    {
        $this->tenant = TenantId::fromString(Ids::next());
        $this->productId = Ids::next();
        $this->imported = new InMemoryImportedOrders();
        $this->outbox = new InMemoryShipmentOutbox();
    }

    public function testQueuesTheConfirmationOfAnOrderThatCameFromTheErpWithTheSkuOfEachProduct(): void
    {
        $this->imported->remember($this->tenant, 'P2026000004', new \DateTimeImmutable());

        $this->handle()($this->event('P2026000004'));

        self::assertCount(1, $this->outbox->rows);
        $confirmation = $this->outbox->rows['shipment-1']->confirmation;
        self::assertSame('P2026000004', $confirmation->orderReference);
        self::assertSame('SEUR', $confirmation->carrier);
        self::assertSame('SE0042261187', $confirmation->trackingNumber);
        self::assertSame(2, $confirmation->parcels);
        self::assertSame('2026-11-09T10:00:00+00:00', $confirmation->shippedAt->format('c'));
        self::assertCount(1, $confirmation->lines);
        self::assertSame('PV27-CAM-LIN-AZU-M', $confirmation->lines[0]->sku);
        self::assertSame(7, $confirmation->lines[0]->units);
    }

    public function testAnOrderThatDidNotComeFromTheErpIsNotQueued(): void
    {
        $this->handle()($this->event('PED-1'));

        self::assertSame([], $this->outbox->rows);
    }

    public function testAnOrderOfAnotherTenantWithTheSameNumberIsNotQueued(): void
    {
        $this->imported->remember(TenantId::fromString(Ids::next()), 'P2026000004', new \DateTimeImmutable());

        $this->handle()($this->event('P2026000004'));

        self::assertSame([], $this->outbox->rows);
    }

    public function testTheSameEventTwiceQueuesOnce(): void
    {
        $this->imported->remember($this->tenant, 'P2026000004', new \DateTimeImmutable());
        $handler = $this->handle();

        $handler($this->event('P2026000004'));
        $handler($this->event('P2026000004'));

        self::assertCount(1, $this->outbox->rows);
    }

    private function event(string $reference): ShipmentLeft
    {
        return new ShipmentLeft($this->tenant, 'shipment-1', $reference, 'SEUR', 'SE0042261187', 2, [new ShippedUnits($this->productId, 7)], new \DateTimeImmutable('2026-11-09 10:00:00', new \DateTimeZone('UTC')));
    }

    private function handle(): QueueShipmentConfirmation
    {
        $productId = $this->productId;
        $catalog = new readonly class($productId) implements ProductCatalog {
            public function __construct(private string $productId)
            {
            }

            #[\Override]
            public function findById(TenantId $tenantId, string $productId): ?ProductSnapshot
            {
                return $productId === $this->productId ? new ProductSnapshot($productId, 'PV27-CAM-LIN-AZU-M', 'Camisa', StorageClass::Ambient, false, false, null, null, true, null, TurnoverClass::B, 350, 250, 40, 250) : null;
            }

            #[\Override]
            public function findByGtin(TenantId $tenantId, string $gtin): ?ProductSnapshot
            {
                return null;
            }

            #[\Override]
            public function findBySku(TenantId $tenantId, string $sku): ?ProductSnapshot
            {
                return null;
            }
        };

        return new QueueShipmentConfirmation($this->imported, $catalog, $this->outbox);
    }
}
