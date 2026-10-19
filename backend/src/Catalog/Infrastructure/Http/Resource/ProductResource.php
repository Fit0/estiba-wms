<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Catalog\Domain\Gtin;
use App\Catalog\Domain\Product;
use App\Catalog\Domain\ProductStatus;
use App\Catalog\Domain\UnitOfMeasure;
use App\Catalog\Infrastructure\Http\Input\AssignGtinInput;
use App\Catalog\Infrastructure\Http\Input\DefinePackagingLevelInput;
use App\Catalog\Infrastructure\Http\Input\RegisterProductInput;
use App\Catalog\Infrastructure\Http\Input\UpdateProductInput;
use App\Catalog\Infrastructure\Http\State\AssignGtinProcessor;
use App\Catalog\Infrastructure\Http\State\DefinePackagingLevelProcessor;
use App\Catalog\Infrastructure\Http\State\ProductProvider;
use App\Catalog\Infrastructure\Http\State\ProductStatusProcessor;
use App\Catalog\Infrastructure\Http\State\RegisterProductProcessor;
use App\Catalog\Infrastructure\Http\State\UpdateProductProcessor;
use App\Shared\Domain\StorageClass;

#[ApiResource(
    shortName: 'Product',
    operations: [
        new GetCollection(uriTemplate: '/products', provider: ProductProvider::class),
        new Get(uriTemplate: '/products/{id}', provider: ProductProvider::class),
        new Post(uriTemplate: '/products', input: RegisterProductInput::class, processor: RegisterProductProcessor::class),
        new Patch(uriTemplate: '/products/{id}', input: UpdateProductInput::class, read: false, processor: UpdateProductProcessor::class),
        new Post(uriTemplate: '/products/{id}/gtins', status: 200, input: AssignGtinInput::class, read: false, processor: AssignGtinProcessor::class),
        new Post(uriTemplate: '/products/{id}/packaging-levels', status: 200, input: DefinePackagingLevelInput::class, read: false, processor: DefinePackagingLevelProcessor::class),
        new Post(
            uriTemplate: '/products/{id}/block',
            status: 200,
            input: false,
            read: false,
            deserialize: false,
            processor: ProductStatusProcessor::class,
            extraProperties: ['target_status' => 'blocked'],
        ),
        new Post(
            uriTemplate: '/products/{id}/unblock',
            status: 200,
            input: false,
            read: false,
            deserialize: false,
            processor: ProductStatusProcessor::class,
            extraProperties: ['target_status' => 'active'],
        ),
        new Post(
            uriTemplate: '/products/{id}/discontinue',
            status: 200,
            input: false,
            read: false,
            deserialize: false,
            processor: ProductStatusProcessor::class,
            extraProperties: ['target_status' => 'discontinued'],
        ),
    ],
)]
final readonly class ProductResource
{
    /**
     * @param list<string>                 $gtins
     * @param list<PackagingLevelResource> $packagingLevels
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $sku,
        public string $name,
        public UnitOfMeasure $baseUnit,
        public StorageClass $storageClass,
        public ProductStatus $status,
        public int $lengthMm,
        public int $widthMm,
        public int $heightMm,
        public int $weightGrams,
        public bool $trackedByLot,
        public bool $trackedByExpiry,
        public ?int $minShelfLifeAtReceiptDays,
        public ?int $minShelfLifeAtShipmentDays,
        public array $gtins,
        public array $packagingLevels,
    ) {
    }

    public static function fromDomain(Product $product): self
    {
        $dimensions = $product->dimensions();
        $tracking = $product->trackingPolicy();

        return new self(
            $product->id()->value,
            $product->sku()->value,
            $product->name()->value,
            $product->baseUnit(),
            $product->storageClass(),
            $product->status(),
            $dimensions->lengthMm,
            $dimensions->widthMm,
            $dimensions->heightMm,
            $dimensions->weightGrams,
            $tracking->trackedByLot,
            $tracking->trackedByExpiry,
            $tracking->minShelfLifeAtReceiptDays,
            $tracking->minShelfLifeAtShipmentDays,
            array_map(static fn (Gtin $gtin): string => $gtin->value, $product->gtins()),
            array_map(PackagingLevelResource::fromDomain(...), $product->packagingLevels()),
        );
    }
}
