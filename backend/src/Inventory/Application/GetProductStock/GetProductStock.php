<?php

declare(strict_types=1);

namespace App\Inventory\Application\GetProductStock;

use App\Inventory\Application\StockOverview\ProductStock;
use App\Shared\Application\Bus\Query;

/**
 * How much the tenant holds of one product, in total and by warehouse. A product of the catalog
 * with no stock has zero everywhere.
 *
 * @implements Query<ProductStock>
 */
final readonly class GetProductStock implements Query
{
    public function __construct(public string $productId)
    {
    }
}
