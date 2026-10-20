<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Shared\Domain\TenantId;

/**
 * One line of the ledger: the stock of one stock item changed by this many units, and held this
 * many afterwards. A movement has no method that changes it and no way to be deleted (STK-04): a
 * mistake is fixed by a new movement that compensates it.
 *
 * Only {@see StockItem} creates movements, so each one is written in the same step as the change
 * it records (STK-03).
 */
final readonly class StockMovement
{
    public function __construct(
        public StockMovementId $id,
        public TenantId $tenantId,
        public StockItemId $stockItemId,
        public MovementType $type,
        public int $change,
        public Quantity $balanceAfter,
        public ?MovementReference $reference,
        public ?AdjustmentReason $reason,
        public AuthorId $author,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
