<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain;

use App\Catalog\Domain\Dimensions;
use App\Catalog\Domain\Event\GtinAssigned;
use App\Catalog\Domain\Event\PackagingLevelDefined;
use App\Catalog\Domain\Event\ProductBlocked;
use App\Catalog\Domain\Event\ProductClassified;
use App\Catalog\Domain\Event\ProductDiscontinued;
use App\Catalog\Domain\Event\ProductRegistered;
use App\Catalog\Domain\Event\ProductUnblocked;
use App\Catalog\Domain\Gtin;
use App\Catalog\Domain\PackagingLevel;
use App\Catalog\Domain\PackagingType;
use App\Catalog\Domain\Product;
use App\Catalog\Domain\ProductName;
use App\Catalog\Domain\ProductStatus;
use App\Catalog\Domain\Sku;
use App\Catalog\Domain\TrackingPolicy;
use App\Catalog\Domain\UnitOfMeasure;
use App\Shared\Domain\ProductFamily;
use App\Shared\Domain\StorageClass;
use App\Shared\Domain\TurnoverClass;
use App\Tests\Unit\Support\AssertsDomainErrors;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    use AssertsDomainErrors;

    public function testRegistersAnActiveProductAndRaisesAnEvent(): void
    {
        $tenantId = ProductMother::tenantId();
        $id = ProductMother::productId();

        $product = Product::register(
            $id,
            $tenantId,
            Sku::fromString(' agua-500 '),
            ProductName::fromString('Agua mineral 500 ml'),
            UnitOfMeasure::Each,
            Dimensions::of(60, 60, 200, 520),
            TrackingPolicy::of(true, true, 90, 30),
            StorageClass::Chilled,
            new InMemoryProductRegistry(),
            ProductMother::at(),
        );

        self::assertTrue($product->id()->equals($id));
        self::assertTrue($product->tenantId()->equals($tenantId));
        self::assertSame('AGUA-500', $product->sku()->value);
        self::assertSame('Agua mineral 500 ml', $product->name()->value);
        self::assertSame(UnitOfMeasure::Each, $product->baseUnit());
        self::assertSame(520, $product->dimensions()->weightGrams);
        self::assertTrue($product->trackingPolicy()->trackedByExpiry);
        self::assertSame(StorageClass::Chilled, $product->storageClass());
        self::assertSame(ProductStatus::Active, $product->status());
        self::assertSame([], $product->gtins());
        self::assertSame([], $product->packagingLevels());
        self::assertTrue($product->isAvailableForOperations());

        $events = $product->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ProductRegistered::class, $events[0]);
        self::assertSame('AGUA-500', $events[0]->sku->value);
        self::assertEquals(ProductMother::at(), $events[0]->occurredAt());
    }

    // CAT-01: the SKU is unique per tenant, ignoring case and surrounding spaces.
    public function testRejectsASkuAlreadyUsedByTheTenantIgnoringCase(): void
    {
        $tenantId = ProductMother::tenantId();
        $registry = new InMemoryProductRegistry()->takeSku($tenantId, Sku::fromString('AGUA-500'));

        self::assertDomainError(
            'catalog.sku_already_used',
            ['sku' => 'AGUA-500'],
            static fn (): Product => ProductMother::product($tenantId, ' agua-500', null, $registry),
        );
    }

    public function testAllowsTheSameSkuInAnotherTenant(): void
    {
        $registry = new InMemoryProductRegistry()->takeSku(ProductMother::tenantId(), Sku::fromString('AGUA-500'));

        $product = ProductMother::product(null, 'AGUA-500', null, $registry);

        self::assertSame('AGUA-500', $product->sku()->value);
    }

    public function testAssignsSeveralGtinsAndRaisesAnEventForEach(): void
    {
        $product = ProductMother::product();
        $product->pullEvents();

        $product->assignGtin(Gtin::fromString(ProductMother::EAN_13), new InMemoryProductRegistry(), ProductMother::at());
        $product->assignGtin(Gtin::fromString('10614141000415'), new InMemoryProductRegistry(), ProductMother::at());

        self::assertCount(2, $product->gtins());
        self::assertSame(ProductMother::EAN_13, $product->gtins()[0]->value);
        $events = $product->pullEvents();
        self::assertCount(2, $events);
        self::assertInstanceOf(GtinAssigned::class, $events[1]);
        self::assertSame('10614141000415', $events[1]->gtin->value);
    }

    // CAT-02: a GTIN belongs to one product of the tenant.
    public function testRejectsAGtinThatAnotherProductOfTheTenantHas(): void
    {
        $product = ProductMother::product();
        $gtin = Gtin::fromString(ProductMother::EAN_13);
        $registry = new InMemoryProductRegistry()->takeGtin($product->tenantId(), $gtin);

        self::assertDomainError(
            'catalog.gtin_already_used',
            ['gtin' => ProductMother::EAN_13],
            static fn () => $product->assignGtin($gtin, $registry, ProductMother::at()),
        );
        self::assertSame([], $product->gtins());
    }

    public function testAllowsAGtinThatOnlyAnotherTenantHas(): void
    {
        $product = ProductMother::product();
        $gtin = Gtin::fromString(ProductMother::EAN_13);
        $registry = new InMemoryProductRegistry()->takeGtin(ProductMother::tenantId(), $gtin);

        $product->assignGtin($gtin, $registry, ProductMother::at());

        self::assertCount(1, $product->gtins());
    }

    // CAT-02: the same barcode printed as UPC and as EAN is one GTIN.
    public function testRejectsTheSameGtinTwiceOnOneProductEvenWithADifferentLength(): void
    {
        $product = ProductMother::product();
        $product->assignGtin(Gtin::fromString(ProductMother::UPC_12), new InMemoryProductRegistry(), ProductMother::at());

        self::assertDomainError(
            'catalog.gtin_already_used',
            ['gtin' => '0036000291452'],
            static fn () => $product->assignGtin(Gtin::fromString('0036000291452'), new InMemoryProductRegistry(), ProductMother::at()),
        );
        self::assertCount(1, $product->gtins());
    }

    public function testDefinesPackagingLevelsFromSmallestToLargest(): void
    {
        $product = ProductMother::product();
        $product->pullEvents();

        $product->definePackagingLevel(PackagingLevel::of(PackagingType::InnerPack, 6), ProductMother::at());
        $product->definePackagingLevel(PackagingLevel::of(PackagingType::Case, 24), ProductMother::at());
        $product->definePackagingLevel(PackagingLevel::of(PackagingType::Pallet, 960), ProductMother::at());

        self::assertSame([6, 24, 960], array_map(static fn (PackagingLevel $level): int => $level->baseUnits, $product->packagingLevels()));
        $events = $product->pullEvents();
        self::assertCount(3, $events);
        self::assertInstanceOf(PackagingLevelDefined::class, $events[2]);
        self::assertSame(PackagingType::Pallet, $events[2]->level->type);
    }

    // CAT-03: the base unit is 1, so the first level has to be larger than 1.
    public function testRejectsAFirstPackagingLevelThatIsNotLargerThanTheBaseUnit(): void
    {
        $product = ProductMother::product();

        self::assertDomainError(
            'catalog.packaging_level_not_larger',
            ['type' => 'case', 'baseUnits' => 1, 'previousBaseUnits' => 1],
            static fn () => $product->definePackagingLevel(PackagingLevel::of(PackagingType::Case, 1), ProductMother::at()),
        );
        self::assertSame([], $product->packagingLevels());
    }

    // CAT-03: each level is larger than the one before.
    public function testRejectsAPackagingLevelThatIsNotLargerThanThePreviousOne(): void
    {
        $product = ProductMother::product();
        $product->definePackagingLevel(PackagingLevel::of(PackagingType::Case, 24), ProductMother::at());

        self::assertDomainError(
            'catalog.packaging_level_not_larger',
            ['type' => 'pallet', 'baseUnits' => 24, 'previousBaseUnits' => 24],
            static fn () => $product->definePackagingLevel(PackagingLevel::of(PackagingType::Pallet, 24), ProductMother::at()),
        );
        self::assertCount(1, $product->packagingLevels());
    }

    // CAT-05: the tracking policy is fixed at registration. Nothing on the product changes it.
    public function testHasNoWayToChangeTheTrackingPolicy(): void
    {
        $policy = TrackingPolicy::of(true, true, 60, 20);
        $product = ProductMother::product(null, 'LACTEO-1', $policy);

        $product->rename(ProductName::fromString('Yogur natural'));
        $product->changeDimensions(Dimensions::of(50, 50, 90, 130));
        $product->block(ProductMother::at());
        $product->unblock(ProductMother::at());

        self::assertSame($policy, $product->trackingPolicy());
        self::assertSame('Yogur natural', $product->name()->value);
        self::assertSame(130, $product->dimensions()->weightGrams);
    }

    // CAT-06: a blocked product cannot be received or allocated.
    public function testBlocksAProductAndRaisesAnEvent(): void
    {
        $product = ProductMother::product();
        $product->pullEvents();

        $product->block(ProductMother::at());

        self::assertSame(ProductStatus::Blocked, $product->status());
        self::assertFalse($product->isAvailableForOperations());
        $events = $product->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ProductBlocked::class, $events[0]);
        self::assertSame('AGUA-500', $events[0]->sku->value);
    }

    public function testRejectsBlockingAProductThatIsAlreadyBlocked(): void
    {
        $product = ProductMother::product();
        $product->block(ProductMother::at());

        self::assertDomainError(
            'catalog.product_already_blocked',
            ['sku' => 'AGUA-500'],
            static fn () => $product->block(ProductMother::at()),
        );
    }

    public function testUnblocksAProductAndRaisesAnEvent(): void
    {
        $product = ProductMother::product();
        $product->block(ProductMother::at());
        $product->pullEvents();

        $product->unblock(ProductMother::at());

        self::assertSame(ProductStatus::Active, $product->status());
        self::assertTrue($product->isAvailableForOperations());
        self::assertInstanceOf(ProductUnblocked::class, $product->pullEvents()[0]);
    }

    public function testRejectsUnblockingAProductThatIsNotBlocked(): void
    {
        $product = ProductMother::product();

        self::assertDomainError(
            'catalog.product_not_blocked',
            ['sku' => 'AGUA-500'],
            static fn () => $product->unblock(ProductMother::at()),
        );
    }

    public function testDiscontinuesAProductFromActiveAndFromBlocked(): void
    {
        $active = ProductMother::product();
        $active->pullEvents();
        $blocked = ProductMother::product();
        $blocked->block(ProductMother::at());
        $blocked->pullEvents();

        $active->discontinue(ProductMother::at());
        $blocked->discontinue(ProductMother::at());

        self::assertSame(ProductStatus::Discontinued, $active->status());
        self::assertSame(ProductStatus::Discontinued, $blocked->status());
        self::assertFalse($active->isAvailableForOperations());
        self::assertInstanceOf(ProductDiscontinued::class, $active->pullEvents()[0]);
    }

    // CAT-08: a discontinued product is final.
    public function testRejectsEveryChangeToADiscontinuedProduct(): void
    {
        $product = ProductMother::product();
        $product->discontinue(ProductMother::at());
        $product->pullEvents();
        $changes = [
            'block' => static fn () => $product->block(ProductMother::at()),
            'unblock' => static fn () => $product->unblock(ProductMother::at()),
            'discontinue' => static fn () => $product->discontinue(ProductMother::at()),
            'assign a GTIN' => static fn () => $product->assignGtin(Gtin::fromString(ProductMother::EAN_13), new InMemoryProductRegistry(), ProductMother::at()),
            'define a packaging level' => static fn () => $product->definePackagingLevel(PackagingLevel::of(PackagingType::Case, 12), ProductMother::at()),
            'rename' => static fn () => $product->rename(ProductName::fromString('Otro nombre')),
            'change dimensions' => static fn () => $product->changeDimensions(Dimensions::of(1, 1, 1, 1)),
        ];

        foreach ($changes as $change) {
            self::assertDomainError('catalog.product_discontinued', ['sku' => 'AGUA-500'], $change);
        }

        self::assertSame(ProductStatus::Discontinued, $product->status());
        self::assertSame([], $product->pullEvents());
    }

    public function testIsClassifiedForPutaway(): void
    {
        $product = ProductMother::product();
        $product->pullEvents();

        self::assertNull($product->family());
        self::assertSame(TurnoverClass::B, $product->turnoverClass(), 'A new product moves at an average pace until someone says otherwise.');

        $product->classify(ProductFamily::fromString('camiseria'), TurnoverClass::A, ProductMother::at());

        self::assertSame('CAMISERIA', $product->family()?->value);
        self::assertSame(TurnoverClass::A, $product->turnoverClass());
        $events = $product->pullEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ProductClassified::class, $events[0]);
        self::assertSame(TurnoverClass::A, $events[0]->turnoverClass);

        $product->classify(null, TurnoverClass::C, ProductMother::at());
        self::assertNull($product->family());
    }

    // CAT-08
    public function testADiscontinuedProductIsNotClassified(): void
    {
        $product = ProductMother::product();
        $product->discontinue(ProductMother::at());

        self::assertDomainError('catalog.product_discontinued', ['sku' => 'AGUA-500'], static fn () => $product->classify(null, TurnoverClass::A, ProductMother::at()));
    }
}
