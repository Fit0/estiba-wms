<?php

declare(strict_types=1);

namespace App\Inventory\Application\RebuildStockOverview;

use App\Inventory\Application\StockOverview\StockOverviewProjection;
use App\Inventory\Application\StockOverview\StockOverviewProjector;
use App\Inventory\Domain\StockItemRepository;
use App\Shared\Application\Bus\CommandHandler;

/**
 * Runs in one transaction, like every command: a reader sees the old read model until the new one
 * is complete.
 */
final readonly class RebuildStockOverviewHandler implements CommandHandler
{
    public function __construct(
        private StockItemRepository $stockItems,
        private StockOverviewProjection $projection,
        private StockOverviewProjector $projector,
    ) {
    }

    public function __invoke(RebuildStockOverview $command): int
    {
        $this->projection->clear();

        $projected = 0;
        foreach ($this->stockItems->all() as $stockItem) {
            $this->projector->project($stockItem);
            ++$projected;
        }

        return $projected;
    }
}
