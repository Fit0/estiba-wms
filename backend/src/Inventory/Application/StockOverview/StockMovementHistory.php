<?php

declare(strict_types=1);

namespace App\Inventory\Application\StockOverview;

use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;
use App\Shared\Domain\TenantId;

/**
 * How the queries read the ledger: the movements themselves, never changed (STK-04).
 */
interface StockMovementHistory
{
    /**
     * The movements of the tenant, newest first. A movement is listed once the read model knows
     * its stock item.
     *
     * @return Page<StockMovementEntry>
     */
    public function search(TenantId $tenantId, MovementSearch $search, PageRequest $page): Page;
}
