<?php

declare(strict_types=1);

namespace App\Inventory\Application\StockOverview;

use App\Inventory\Domain\MovementType;
use App\Inventory\Domain\StockStatus;

/**
 * One movement of the ledger, with the stock item it changed as the read model describes it.
 */
final readonly class StockMovementEntry
{
    public function __construct(
        public string $id,
        public string $stockItemId,
        public string $productId,
        public string $sku,
        public string $locationId,
        public string $locationCode,
        public ?string $lot,
        public ?\DateTimeImmutable $expiryDate,
        public StockStatus $status,
        public MovementType $type,
        public int $change,
        public int $balanceAfter,
        public ?string $reference,
        public ?string $reason,
        public string $authorId,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
