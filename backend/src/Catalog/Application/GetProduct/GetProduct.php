<?php

declare(strict_types=1);

namespace App\Catalog\Application\GetProduct;

use App\Catalog\Domain\Product;
use App\Shared\Application\Bus\Query;

/**
 * One product of the current tenant.
 *
 * @implements Query<Product>
 */
final readonly class GetProduct implements Query
{
    public function __construct(public string $productId)
    {
    }
}
