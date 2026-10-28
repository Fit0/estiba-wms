<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog\Persistence;

use App\Catalog\Domain\Gtin;
use App\Catalog\Domain\PackagingLevel;
use App\Catalog\Domain\PackagingType;
use App\Catalog\Domain\Product;
use App\Catalog\Domain\ProductName;
use App\Catalog\Domain\ProductStatus;
use App\Catalog\Domain\Sku;
use App\Catalog\Domain\TrackingPolicy;
use App\Catalog\Infrastructure\Persistence\Doctrine\DoctrineProductRepository;
use App\Shared\Domain\PageRequest;
use App\Tests\Functional\Warehouse\Persistence\PersistenceTestCase;
use App\Tests\Unit\Catalog\Domain\InMemoryProductRegistry;
use App\Tests\Unit\Catalog\Domain\ProductMother;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

final class DoctrineProductRepositoryTest extends PersistenceTestCase
{
    private DoctrineProductRepository $repository;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new DoctrineProductRepository($this->entityManager);
    }

    public function testFindsASavedProductWithEverythingItHolds(): void
    {
        $product = ProductMother::product(null, 'LACTEO-1', TrackingPolicy::of(true, true, 60, 20));
        $product->assignGtin(Gtin::fromString(ProductMother::EAN_13), new InMemoryProductRegistry(), ProductMother::at());
        $product->assignGtin(Gtin::fromString(ProductMother::UPC_12), new InMemoryProductRegistry(), ProductMother::at());
        $product->definePackagingLevel(PackagingLevel::of(PackagingType::Case, 12), ProductMother::at());
        $product->definePackagingLevel(PackagingLevel::of(PackagingType::Pallet, 480), ProductMother::at());
        $product->block(ProductMother::at());
        $this->repository->save($product);
        $this->flushAndForget();

        $found = $this->repository->find($product->id());

        self::assertNotNull($found);
        self::assertTrue($found->tenantId()->equals($product->tenantId()));
        self::assertSame('LACTEO-1', $found->sku()->value);
        self::assertSame('Agua mineral 500 ml', $found->name()->value);
        self::assertSame($product->baseUnit(), $found->baseUnit());
        self::assertSame($product->storageClass(), $found->storageClass());
        self::assertSame(ProductStatus::Blocked, $found->status());
        self::assertEquals(ProductMother::dimensions(), $found->dimensions());
        self::assertEquals(TrackingPolicy::of(true, true, 60, 20), $found->trackingPolicy());
        self::assertSame([ProductMother::EAN_13, ProductMother::UPC_12], array_map(static fn (Gtin $gtin): string => $gtin->value, $found->gtins()));
        self::assertEquals([PackagingLevel::of(PackagingType::Case, 12), PackagingLevel::of(PackagingType::Pallet, 480)], $found->packagingLevels());
    }

    public function testKeepsAProductWithoutTrackingAsUntracked(): void
    {
        $product = ProductMother::product();
        $this->repository->save($product);
        $this->flushAndForget();

        $found = $this->repository->find($product->id());

        self::assertNotNull($found);
        self::assertEquals(TrackingPolicy::none(), $found->trackingPolicy());
        self::assertSame([], $found->gtins());
        self::assertSame([], $found->packagingLevels());
    }

    public function testFindsNothingForAnUnknownProduct(): void
    {
        self::assertNull($this->repository->find(ProductMother::productId()));
    }

    public function testSavesChangesToAnExistingProduct(): void
    {
        $product = ProductMother::product();
        $this->repository->save($product);
        $this->flushAndForget();

        $loaded = $this->repository->find($product->id());
        self::assertNotNull($loaded);
        $loaded->assignGtin(Gtin::fromString(ProductMother::EAN_13), $this->repository, ProductMother::at());
        $loaded->discontinue(ProductMother::at());
        $this->repository->save($loaded);
        $this->flushAndForget();

        $reloaded = $this->repository->find($product->id());
        self::assertNotNull($reloaded);
        self::assertSame(ProductStatus::Discontinued, $reloaded->status());
        self::assertCount(1, $reloaded->gtins());
    }

    public function testSearchesByTextInTheSkuOrTheNameOfTheTenantOnly(): void
    {
        $shirt = ProductMother::product(null, 'CAM-LIN');
        $shirt->rename(ProductName::fromString('Camisa de Lino'));
        $other = ProductMother::product($shirt->tenantId(), 'AGUA-500');
        $foreign = ProductMother::product(null, 'CAM-LIN');
        $foreign->rename(ProductName::fromString('Camisa de lino'));
        foreach ([$shirt, $other, $foreign] as $product) {
            $this->repository->save($product);
        }
        $this->flushAndForget();
        $tenant = $shirt->tenantId();

        $byName = $this->repository->search($tenant, null, null, 'LINO', PageRequest::of());
        $bySku = $this->repository->search($tenant, null, null, 'agua', PageRequest::of());
        $wildcard = $this->repository->search($tenant, null, null, '%', PageRequest::of());

        self::assertSame(['CAM-LIN'], array_map(static fn (Product $p): string => $p->sku()->value, $byName->items));
        self::assertSame(['AGUA-500'], array_map(static fn (Product $p): string => $p->sku()->value, $bySku->items));
        self::assertSame(0, $wildcard->total);
    }

    public function testReportsASkuAsTakenOnlyWithinTheSameTenant(): void
    {
        $product = ProductMother::product();
        $this->repository->save($product);
        $this->flushAndForget();
        $sku = Sku::fromString('agua-500');

        self::assertTrue($this->repository->isSkuTaken($product->tenantId(), $sku));
        self::assertFalse($this->repository->isSkuTaken(ProductMother::tenantId(), $sku));
        self::assertFalse($this->repository->isSkuTaken($product->tenantId(), Sku::fromString('OTRO-1')));
    }

    // CAT-01: the database backs the registry check against two requests racing.
    public function testRejectsTwoProductsWithTheSameSkuForATenant(): void
    {
        $first = ProductMother::product();
        $second = ProductMother::product($first->tenantId());
        $this->repository->save($first);
        $this->repository->save($second);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->entityManager->flush();
    }

    public function testReportsAGtinAsTakenOnlyWithinTheSameTenantAndWhateverItsLength(): void
    {
        $product = ProductMother::product();
        $product->assignGtin(Gtin::fromString(ProductMother::UPC_12), new InMemoryProductRegistry(), ProductMother::at());
        $this->repository->save($product);
        $this->flushAndForget();

        self::assertTrue($this->repository->isGtinTaken($product->tenantId(), Gtin::fromString(ProductMother::UPC_12)));
        self::assertTrue($this->repository->isGtinTaken($product->tenantId(), Gtin::fromString('0'.ProductMother::UPC_12)));
        self::assertFalse($this->repository->isGtinTaken(ProductMother::tenantId(), Gtin::fromString(ProductMother::UPC_12)));
        self::assertFalse($this->repository->isGtinTaken($product->tenantId(), Gtin::fromString(ProductMother::EAN_13)));
    }

    public function testFindsAProductByAnyLengthOfItsGtin(): void
    {
        $product = ProductMother::product();
        $product->assignGtin(Gtin::fromString(ProductMother::UPC_12), new InMemoryProductRegistry(), ProductMother::at());
        $this->repository->save($product);
        $this->flushAndForget();

        $byUpc = $this->repository->findByGtin($product->tenantId(), Gtin::fromString(ProductMother::UPC_12));
        $byEan = $this->repository->findByGtin($product->tenantId(), Gtin::fromString('0'.ProductMother::UPC_12));

        self::assertNotNull($byUpc);
        self::assertTrue($byUpc->id()->equals($product->id()));
        self::assertNotNull($byEan);
        self::assertTrue($byEan->id()->equals($product->id()));
        self::assertNull($this->repository->findByGtin(ProductMother::tenantId(), Gtin::fromString(ProductMother::UPC_12)), 'A GTIN of another tenant is not found.');
        self::assertNull($this->repository->findByGtin($product->tenantId(), Gtin::fromString(ProductMother::EAN_13)));
    }

    // CAT-02: the database backs the registry check against two requests racing.
    public function testRejectsTheSameGtinOnTwoProductsOfATenant(): void
    {
        $first = ProductMother::product();
        $second = ProductMother::product($first->tenantId(), 'AGUA-1500');
        $first->assignGtin(Gtin::fromString(ProductMother::EAN_13), new InMemoryProductRegistry(), ProductMother::at());
        $this->repository->save($first);
        $this->flushAndForget();

        // Two requests that both passed the registry check before either saved.
        $second->assignGtin(Gtin::fromString(ProductMother::EAN_13), new InMemoryProductRegistry(), ProductMother::at());
        $this->repository->save($second);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->entityManager->flush();
    }
}
