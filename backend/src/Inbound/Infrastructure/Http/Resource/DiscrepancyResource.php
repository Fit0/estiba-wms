<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Http\Resource;

use App\Inbound\Domain\Discrepancy;
use App\Inbound\Domain\DiscrepancyType;

/**
 * A difference between what a receipt expected and what arrived.
 */
final readonly class DiscrepancyResource
{
    public function __construct(
        public DiscrepancyType $type,
        public string $productId,
        public int $units,
    ) {
    }

    public static function fromDomain(Discrepancy $discrepancy): self
    {
        return new self($discrepancy->type, $discrepancy->productId->value, $discrepancy->units);
    }
}
