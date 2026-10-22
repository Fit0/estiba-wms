<?php

declare(strict_types=1);

namespace App\Inventory\Application\RebuildStockOverview;

use App\Shared\Application\Bus\Command;

/**
 * Builds the stock read model again from the ledger, for every tenant: after it was lost or went
 * wrong, or when a product name or a location code it copied has changed (ADR 0013). It returns how
 * many stock items it projected.
 *
 * @implements Command<int>
 */
final readonly class RebuildStockOverview implements Command
{
}
