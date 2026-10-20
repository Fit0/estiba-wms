<?php

declare(strict_types=1);

namespace App\Catalog\Application\GetProduct;

use App\Catalog\Application\ProductLookup;
use App\Catalog\Domain\Exception\ProductNotFound;
use App\Catalog\Domain\Product;
use App\Shared\Application\Bus\QueryHandler;

final readonly class GetProductHandler implements QueryHandler
{
    public function __construct(private ProductLookup $lookup)
    {
    }

    /**
     * @throws ProductNotFound
     */
    public function __invoke(GetProduct $query): Product
    {
        return $this->lookup->get($query->productId);
    }
}
