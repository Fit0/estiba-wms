<?php

declare(strict_types=1);

namespace App\Inventory\Application\Contract;

use App\Shared\Domain\TenantId;

/**
 * Units of a product that arrived at a location, such as goods counted at a dock. Units that
 * arrived damaged go in quarantine, never available (INB-10).
 */
final readonly class StockArrival
{
    public function __construct(
        public TenantId $tenantId,
        public string $productId,
        public string $locationId,
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
