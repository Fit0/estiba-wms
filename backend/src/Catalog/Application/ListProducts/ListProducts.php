<?php

declare(strict_types=1);

namespace App\Catalog\Application\ListProducts;

use App\Catalog\Domain\Product;
use App\Catalog\Domain\ProductStatus;
use App\Shared\Application\Bus\Query;
use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;

/**
 * @implements Query<Page<Product>>
 */
final readonly class ListProducts implements Query
{
    public function __construct(
        public PageRequest $page,
        public ?string $skuPrefix = null,
        public ?ProductStatus $status = null,
        public ?string $text = null,
    ) {
    }
}
