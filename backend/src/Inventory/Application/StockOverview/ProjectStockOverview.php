<?php

declare(strict_types=1);

namespace App\Inventory\Application\StockOverview;

use App\Inventory\Domain\Event\StockMovementRecorded;
use App\Inventory\Domain\StockItem;
use App\Inventory\Domain\StockItemRepository;
use App\Shared\Application\Bus\EventHandler;

/**
 * Whenever a movement is recorded, the read model copies its stock item again. The worker handles
 * it a moment after the movement commits (ADR 0013). It is idempotent without a mark of its own:
 * it writes the current state, so a message handled twice writes the same row twice.
 */
final readonly class ProjectStockOverview implements EventHandler
{
    public function __construct(
        private StockItemRepository $stockItems,
        private StockOverviewProjector $projector,
    ) {
    }

    public function __invoke(StockMovementRecorded $event): void
    {
        $stockItem = $this->stockItems->find($event->stockItemId);
        if (!$stockItem instanceof StockItem) {
            throw new \LogicException('A stock item that recorded a movement must exist.');
        }

        $this->projector->project($stockItem);
    }
}
