<?php

declare(strict_types=1);

namespace App\Catalog\Application\ListProducts;

use App\Catalog\Domain\Product;
use App\Catalog\Domain\ProductRepository;
use App\Shared\Application\Bus\QueryHandler;
use App\Shared\Application\CurrentTenant;
use App\Shared\Domain\Page;

final readonly class ListProductsHandler implements QueryHandler
{
    public function __construct(
        private ProductRepository $products,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @return Page<Product>
     */
    public function __invoke(ListProducts $query): Page
    {
        return $this->products->search($this->tenant->id(), $query->skuPrefix, $query->status, $query->text, $query->page);
    }
}
