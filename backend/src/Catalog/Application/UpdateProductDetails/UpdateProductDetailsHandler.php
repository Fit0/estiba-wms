<?php

declare(strict_types=1);

namespace App\Catalog\Application\UpdateProductDetails;

use App\Catalog\Application\ProductLookup;
use App\Catalog\Domain\Dimensions;
use App\Catalog\Domain\Product;
use App\Catalog\Domain\ProductName;

final readonly class UpdateProductDetailsHandler
{
    public function __construct(private ProductLookup $lookup)
    {
    }

    public function __invoke(UpdateProductDetails $command): Product
    {
        $product = $this->lookup->get($command->productId);

        if (null !== $command->name) {
            $product->rename(ProductName::fromString($command->name));
        }

        if ($command->changesDimensions()) {
            $current = $product->dimensions();
            $product->changeDimensions(Dimensions::of(
                $command->lengthMm ?? $current->lengthMm,
                $command->widthMm ?? $current->widthMm,
                $command->heightMm ?? $current->heightMm,
                $command->weightGrams ?? $current->weightGrams,
            ));
        }

        return $product;
    }
}
