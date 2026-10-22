<?php

declare(strict_types=1);

namespace App\Inventory\Application\StockOverview;

/**
 * Where the stock read model is written (ADR 0013). Only {@see StockOverviewProjector} writes it.
 */
interface StockOverviewProjection
{
    /**
     * Adds the entry, or replaces the one of the same stock item. Saving the same entry twice
     * leaves the read model as saving it once.
     */
    public function save(StockOverviewEntry $entry): void;

    /**
     * Empties the read model of every tenant, before it is built again from the ledger.
     */
    public function clear(): void;
}
