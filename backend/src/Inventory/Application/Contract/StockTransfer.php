<?php

declare(strict_types=1);

namespace App\Inventory\Application\Contract;

use App\Shared\Domain\TenantId;

/**
 * Units of a product that move from one location to another, keeping their lot, expiry date and
 * status, such as goods put away from a dock.
 */
final readonly class StockTransfer
{
    public function __construct(
        public TenantId $tenantId,
        public string $productId,
        public string $fromLocationId,
        public string $toLocationId,
        public ?string $lot,
        public ?\DateTimeImmutable $expiryDate,
        public bool $quarantine,
        public int $units,
        public string $reference,
        public string $authorId,
        public \DateTimeImmutable $at,
    ) {
    }
}
