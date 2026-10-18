<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain;

use App\Catalog\Domain\Dimensions;
use App\Catalog\Domain\Product;
use App\Catalog\Domain\ProductId;
use App\Catalog\Domain\ProductName;
use App\Catalog\Domain\Sku;
use App\Catalog\Domain\TrackingPolicy;
use App\Catalog\Domain\UnitOfMeasure;
use App\Shared\Domain\StorageClass;
use App\Shared\Domain\TenantId;
use App\Tests\Unit\Support\Ids;

/**
 * Builds valid objects so each test only spells out what it is about.
 */
final class ProductMother
{
    /** A valid GTIN-13. */
    public const string EAN_13 = '4006381333931';

    /** The same item as a GTIN-12 once a leading zero is added: 0 + 036000291452 is a valid EAN-13. */
    public const string UPC_12 = '036000291452';

    public static function at(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-16T10:00:00+00:00');
    }

    public static function tenantId(): TenantId
    {
        return TenantId::fromString(Ids::next());
    }

    public static function productId(): ProductId
    {
        return ProductId::fromString(Ids::next());
    }

    public static function dimensions(): Dimensions
    {
        return Dimensions::of(120, 80, 60, 450);
    }

    /**
     * A new active product with no GTIN and no packaging, with its registration event still pending.
     */
    public static function product(
        ?TenantId $tenantId = null,
        string $sku = 'AGUA-500',
        ?TrackingPolicy $trackingPolicy = null,
        ?InMemoryProductRegistry $registry = null,
    ): Product {
        return Product::register(
            self::productId(),
            $tenantId ?? self::tenantId(),
            Sku::fromString($sku),
            ProductName::fromString('Agua mineral 500 ml'),
            UnitOfMeasure::Each,
            self::dimensions(),
            $trackingPolicy ?? TrackingPolicy::none(),
            StorageClass::Ambient,
            $registry ?? new InMemoryProductRegistry(),
            self::at(),
        );
    }
}
