<?php

declare(strict_types=1);

namespace App\Inventory\Application\GetStockItem;

use App\Inventory\Application\StockOverview\StockOverviewEntry;
use App\Shared\Application\Bus\Query;

/**
 * @implements Query<StockOverviewEntry>
 */
final readonly class GetStockItem implements Query
{
    public function __construct(public string $id)
    {
    }
}
