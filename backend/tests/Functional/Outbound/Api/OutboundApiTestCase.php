<?php

declare(strict_types=1);

namespace App\Tests\Functional\Outbound\Api;

use App\Inventory\Domain\AuthorId;
use App\Inventory\Domain\LocationId;
use App\Inventory\Domain\LotNumber;
use App\Inventory\Domain\ProductId;
use App\Inventory\Domain\Quantity;
use App\Inventory\Domain\StockItem;
use App\Inventory\Domain\StockItemId;
use App\Inventory\Domain\StockKey;
use App\Inventory\Domain\StockMovementId;
use App\Inventory\Domain\StockStatus;
use App\Inventory\Infrastructure\Persistence\Doctrine\DoctrineStockItemRepository;
use App\Shared\Domain\TenantId;
use App\Tests\Functional\Support\ApiTestCase;
use App\Tests\Unit\Support\Ids;
use Doctrine\ORM\EntityManagerInterface;

/**
 * A warehouse with a few locations and two products, and a way to put stock in it exactly as the
 * test needs: with an arrival date, a lot, an expiry date and a status of its own.
 */
abstract class OutboundApiTestCase extends ApiTestCase
{
    protected string $warehouseId;

    protected string $otherWarehouseId;

    protected const string SHIRT_GTIN = '8412345678905';

    protected const string YOGHURT_GTIN = '8412345678929';

    protected string $shirtId;

    /** Tracked by lot and expiry date, and it must reach the customer with 30 days left. */
    protected string $yoghurtId;

    protected string $reserveId;

    protected string $secondReserveId;

    protected string $pickFaceId;

    protected string $dockId;

    protected string $stagingId;

    protected string $otherWarehouseLocationId;

    /** The zone of the main warehouse, for tests that need more locations. */
    protected string $zoneId;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->shirtId = $this->product('CAM-001', gtin: self::SHIRT_GTIN);
        $this->yoghurtId = $this->product('YOG-001', ['trackedByLot' => true, 'trackedByExpiry' => true, 'minShelfLifeAtShipmentDays' => 30], self::YOGHURT_GTIN);

        $this->warehouseId = $this->id($this->request('POST', '/api/warehouses', ['code' => 'ZGZ1', 'name' => 'Zaragoza']));
        $zone = $this->id($this->request('POST', '/api/warehouses/'.$this->warehouseId.'/zones', ['code' => 'ALM', 'name' => 'Almacén', 'storageClass' => 'ambient']));
        $this->zoneId = $zone;
        $this->reserveId = $this->location($this->warehouseId, $zone, 'A-01-01', 'reserve');
        $this->secondReserveId = $this->location($this->warehouseId, $zone, 'A-01-02', 'reserve');
        $this->pickFaceId = $this->location($this->warehouseId, $zone, 'B-01-01', 'pick_face');
        $this->dockId = $this->location($this->warehouseId, $zone, 'D-01-01', 'dock');
        $this->stagingId = $this->location($this->warehouseId, $zone, 'E-01-01', 'staging');

        $this->otherWarehouseId = $this->id($this->request('POST', '/api/warehouses', ['code' => 'MAD1', 'name' => 'Madrid']));
        $otherZone = $this->id($this->request('POST', '/api/warehouses/'.$this->otherWarehouseId.'/zones', ['code' => 'ALM', 'name' => 'Almacén', 'storageClass' => 'ambient']));
        $this->otherWarehouseLocationId = $this->location($this->otherWarehouseId, $otherZone, 'A-01-01', 'reserve');
    }

    /**
     * Registers an order served from the main warehouse, unless told otherwise.
     *
     * @param array<array-key, mixed>|null $lines each one an object with `productId` and `units`, or anything else to see it refused
     * @param array<string, mixed>         $extra
     *
     * @return array<array-key, mixed>
     */
    protected function registerOrder(string $reference = 'PED-2027-0001', ?array $lines = null, array $extra = [], ?string $tenant = null): array
    {
        return $this->request('POST', '/api/orders', $extra + [
            'reference' => $reference,
            'warehouseId' => $this->warehouseId,
            'customer' => 'Marea Moda Zaragoza',
            'shipTo' => ['street' => 'Calle Mayor 12', 'postalCode' => '50001', 'city' => 'Zaragoza', 'countryCode' => 'ES'],
            'cutOffAt' => '2026-10-24T14:00:00+00:00',
            'lines' => $lines ?? [['productId' => $this->shirtId, 'units' => 10]],
        ], $tenant);
    }

    /**
     * @param array<array-key, mixed> $order
     *
     * @return array<array-key, mixed>
     */
    protected function allocate(array $order): array
    {
        return $this->request('POST', '/api/orders/'.$this->id($order).'/allocate');
    }

    /**
     * Puts units of a product in a location, as a receipt would have, and returns the id of the stock item.
     */
    protected function stock(string $productId, string $locationId, int $units, ?string $lot = null, ?string $expiry = null, string $received = '2026-09-01', StockStatus $status = StockStatus::Available): string
    {
        $key = new StockKey(
            ProductId::fromString($productId),
            LocationId::fromString($locationId),
            null === $lot ? null : LotNumber::fromString($lot),
            null === $expiry ? null : new \DateTimeImmutable($expiry),
            $status,
        );
        $stockItem = StockItem::open(StockItemId::fromString(Ids::next()), TenantId::fromString($this->tenant), $key);
        $stockItem->receive(StockMovementId::fromString(Ids::next()), Quantity::of($units), null, AuthorId::fromString(Ids::next()), new \DateTimeImmutable($received));

        $entityManager = $this->entityManager();
        new DoctrineStockItemRepository($entityManager)->save($stockItem);
        $entityManager->flush();
        $entityManager->clear();

        return $stockItem->id()->value;
    }

    /**
     * The units promised in a stock item, read from the database.
     */
    protected function reservedIn(string $stockItemId): int
    {
        $entityManager = $this->entityManager();
        $this->seeEveryTenant($entityManager);
        $stockItem = $entityManager->find(StockItem::class, StockItemId::fromString($stockItemId));
        self::assertInstanceOf(StockItem::class, $stockItem);

        return $stockItem->reserved()->units;
    }

    /**
     * @param array<array-key, mixed> $order
     *
     * @return array<array-key, mixed>
     */
    protected function lineOf(array $order, int $lineNumber): array
    {
        $lines = $order['lines'] ?? null;
        self::assertIsArray($lines);
        $line = $lines[$lineNumber - 1] ?? null;
        self::assertIsArray($line);

        return $line;
    }

    protected function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * The last request left the tenant filter on for its tenant. A test that checks the database
     * directly, such as stock of another tenant that must not have been touched, looks past it.
     */
    protected function seeEveryTenant(EntityManagerInterface $entityManager): void
    {
        if ($entityManager->getFilters()->isEnabled('tenant')) {
            $entityManager->getFilters()->disable('tenant');
        }
        $entityManager->clear();
    }

    /**
     * @param array<string, mixed> $tracking
     */
    private function product(string $sku, array $tracking = [], ?string $gtin = null): string
    {
        $id = $this->id($this->request('POST', '/api/products', $tracking + [
            'sku' => $sku,
            'name' => 'Producto '.$sku,
            'baseUnit' => 'each',
            'storageClass' => 'ambient',
            'lengthMm' => 300,
            'widthMm' => 200,
            'heightMm' => 30,
            'weightGrams' => 250,
        ]));

        if (null !== $gtin) {
            $this->request('POST', '/api/products/'.$id.'/gtins', ['gtin' => $gtin]);
        }

        return $id;
    }

    /**
     * @param array<string, mixed> $extra such as a pick sequence
     */
    protected function location(string $warehouseId, string $zoneId, string $code, string $type, array $extra = []): string
    {
        return $this->id($this->request('POST', '/api/warehouses/'.$warehouseId.'/locations', ['zoneId' => $zoneId, 'code' => $code, 'type' => $type] + $extra));
    }
}
