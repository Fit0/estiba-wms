<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Http\Resource;

use App\Inbound\Domain\ExpectedItem;

/**
 * What a receipt expects of one product.
 */
final readonly class ExpectedItemResource
{
    public function __construct(
        public string $productId,
        public int $units,
    ) {
    }

    public static function fromDomain(ExpectedItem $item): self
    {
        return new self($item->productId->value, $item->units);
    }
}
