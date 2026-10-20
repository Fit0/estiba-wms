<?php

declare(strict_types=1);

namespace App\Catalog\Application\RegisterProduct;

use App\Catalog\Domain\Dimensions;
use App\Catalog\Domain\Product;
use App\Catalog\Domain\ProductId;
use App\Catalog\Domain\ProductName;
use App\Catalog\Domain\ProductRepository;
use App\Catalog\Domain\Sku;
use App\Catalog\Domain\TrackingPolicy;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Clock;
use App\Shared\Application\CurrentTenant;
use App\Shared\Application\IdGenerator;

/**
 * The events the product raises stay on it until events are published (E03-05).
 * The command bus owns the transaction and flushes.
 */
final readonly class RegisterProductHandler implements CommandHandler
{
    public function __construct(
        private ProductRepository $products,
        private CurrentTenant $tenant,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    public function __invoke(RegisterProduct $command): Product
    {
        $product = Product::register(
            ProductId::fromString($this->ids->next()),
            $this->tenant->id(),
            Sku::fromString($command->sku),
            ProductName::fromString($command->name),
            $command->baseUnit,
            Dimensions::of($command->lengthMm, $command->widthMm, $command->heightMm, $command->weightGrams),
            TrackingPolicy::of(
                $command->trackedByLot,
                $command->trackedByExpiry,
                $command->minShelfLifeAtReceiptDays,
                $command->minShelfLifeAtShipmentDays,
            ),
            $command->storageClass,
            $this->products,
            $this->clock->now(),
        );

        $this->products->save($product);

        return $product;
    }
}
