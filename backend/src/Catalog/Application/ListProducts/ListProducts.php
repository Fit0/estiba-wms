<?php

declare(strict_types=1);

namespace App\Catalog\Application\ListProducts;

use App\Catalog\Domain\ProductStatus;
use App\Shared\Domain\PageRequest;

final readonly class ListProducts
{
    public function __construct(
        public PageRequest $page,
        public ?string $skuPrefix = null,
        public ?ProductStatus $status = null,
    ) {
    }
}
