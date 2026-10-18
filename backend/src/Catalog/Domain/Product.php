<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Catalog\Domain\Event\GtinAssigned;
use App\Catalog\Domain\Event\PackagingLevelDefined;
use App\Catalog\Domain\Event\ProductBlocked;
use App\Catalog\Domain\Event\ProductDiscontinued as ProductDiscontinuedEvent;
use App\Catalog\Domain\Event\ProductRegistered;
use App\Catalog\Domain\Event\ProductUnblocked;
use App\Catalog\Domain\Exception\GtinAlreadyUsed;
use App\Catalog\Domain\Exception\PackagingLevelNotLarger;
use App\Catalog\Domain\Exception\ProductAlreadyBlocked;
use App\Catalog\Domain\Exception\ProductDiscontinued;
use App\Catalog\Domain\Exception\ProductNotBlocked;
use App\Catalog\Domain\Exception\SkuAlreadyUsed;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\StorageClass;
use App\Shared\Domain\TenantId;

/**
 * Something the warehouse stores, and everything the other contexts need to know about it:
 * how to find it (SKU, GTINs), how it is counted (base unit, packaging), how it must be
 * tracked and stored, and whether it may be used.
 *
 * The tracking policy is fixed at registration (CAT-05): there is no method to change it.
 * A product that needs another policy is registered again under a new SKU.
 */
final class Product extends AggregateRoot
{
    private ProductStatus $status = ProductStatus::Active;

    /** @var list<Gtin> */
    private array $gtins = [];

    /** @var list<PackagingLevel> */
    private array $packagingLevels = [];

    private function __construct(
        private readonly ProductId $id,
        private readonly TenantId $tenantId,
        private readonly Sku $sku,
        private ProductName $name,
        private readonly UnitOfMeasure $baseUnit,
        private Dimensions $dimensions,
        private readonly TrackingPolicy $trackingPolicy,
        private readonly StorageClass $storageClass,
    ) {
    }

    /**
     * @throws SkuAlreadyUsed CAT-01
     */
    public static function register(
        ProductId $id,
        TenantId $tenantId,
        Sku $sku,
        ProductName $name,
        UnitOfMeasure $baseUnit,
        Dimensions $dimensions,
        TrackingPolicy $trackingPolicy,
        StorageClass $storageClass,
        SkuRegistry $registry,
        \DateTimeImmutable $at,
    ): self {
        if ($registry->isSkuTaken($tenantId, $sku)) {
            throw SkuAlreadyUsed::forSku($sku);
        }

        $product = new self($id, $tenantId, $sku, $name, $baseUnit, $dimensions, $trackingPolicy, $storageClass);
        $product->record(new ProductRegistered($id, $tenantId, $sku, $storageClass, $at));

        return $product;
    }

    /**
     * @throws GtinAlreadyUsed     CAT-02
     * @throws ProductDiscontinued CAT-08
     */
    public function assignGtin(Gtin $gtin, GtinRegistry $registry, \DateTimeImmutable $at): void
    {
        $this->assertNotDiscontinued();

        if ($this->hasGtin($gtin) || $registry->isGtinTaken($this->tenantId, $gtin)) {
            throw GtinAlreadyUsed::forGtin($gtin);
        }

        $this->gtins[] = $gtin;
        $this->record(new GtinAssigned($this->id, $gtin, $at));
    }

    /**
     * Levels are defined from the smallest to the largest.
     *
     * @throws PackagingLevelNotLarger CAT-03
     * @throws ProductDiscontinued     CAT-08
     */
    public function definePackagingLevel(PackagingLevel $level, \DateTimeImmutable $at): void
    {
        $this->assertNotDiscontinued();

        $previousBaseUnits = $this->largestPackagingLevel();
        if ($level->baseUnits <= $previousBaseUnits) {
            throw PackagingLevelNotLarger::than($level, $previousBaseUnits);
        }

        $this->packagingLevels[] = $level;
        $this->record(new PackagingLevelDefined($this->id, $level, $at));
    }

    public function rename(ProductName $name): void
    {
        $this->assertNotDiscontinued();

        $this->name = $name;
    }

    public function changeDimensions(Dimensions $dimensions): void
    {
        $this->assertNotDiscontinued();

        $this->dimensions = $dimensions;
    }

    /**
     * @throws ProductAlreadyBlocked CAT-06
     * @throws ProductDiscontinued   CAT-08
     */
    public function block(\DateTimeImmutable $at): void
    {
        $this->assertNotDiscontinued();

        if (ProductStatus::Blocked === $this->status) {
            throw ProductAlreadyBlocked::forSku($this->sku);
        }

        $this->status = ProductStatus::Blocked;
        $this->record(new ProductBlocked($this->id, $this->sku, $at));
    }

    /**
     * @throws ProductNotBlocked   CAT-06
     * @throws ProductDiscontinued CAT-08
     */
    public function unblock(\DateTimeImmutable $at): void
    {
        $this->assertNotDiscontinued();

        if (ProductStatus::Blocked !== $this->status) {
            throw ProductNotBlocked::forSku($this->sku);
        }

        $this->status = ProductStatus::Active;
        $this->record(new ProductUnblocked($this->id, $this->sku, $at));
    }

    /**
     * @throws ProductDiscontinued CAT-08
     */
    public function discontinue(\DateTimeImmutable $at): void
    {
        $this->assertNotDiscontinued();

        $this->status = ProductStatus::Discontinued;
        $this->record(new ProductDiscontinuedEvent($this->id, $this->sku, $at));
    }

    /**
     * Whether the product may be received or allocated (CAT-06).
     */
    public function isAvailableForOperations(): bool
    {
        return ProductStatus::Active === $this->status;
    }

    public function id(): ProductId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function sku(): Sku
    {
        return $this->sku;
    }

    public function name(): ProductName
    {
        return $this->name;
    }

    public function baseUnit(): UnitOfMeasure
    {
        return $this->baseUnit;
    }

    public function dimensions(): Dimensions
    {
        return $this->dimensions;
    }

    public function trackingPolicy(): TrackingPolicy
    {
        return $this->trackingPolicy;
    }

    public function storageClass(): StorageClass
    {
        return $this->storageClass;
    }

    public function status(): ProductStatus
    {
        return $this->status;
    }

    /**
     * @return list<Gtin>
     */
    public function gtins(): array
    {
        return $this->gtins;
    }

    /**
     * @return list<PackagingLevel>
     */
    public function packagingLevels(): array
    {
        return $this->packagingLevels;
    }

    private function hasGtin(Gtin $gtin): bool
    {
        return array_any($this->gtins, static fn (Gtin $own): bool => $own->equals($gtin));
    }

    /**
     * The base unit counts as 1, so the first level has to hold more than one.
     */
    private function largestPackagingLevel(): int
    {
        $last = end($this->packagingLevels);

        return false === $last ? 1 : $last->baseUnits;
    }

    private function assertNotDiscontinued(): void
    {
        if (ProductStatus::Discontinued === $this->status) {
            throw ProductDiscontinued::forSku($this->sku);
        }
    }
}
