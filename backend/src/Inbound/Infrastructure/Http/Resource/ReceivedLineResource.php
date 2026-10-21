<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Http\Resource;

use App\Inbound\Domain\ItemCondition;
use App\Inbound\Domain\ReceivedLine;

/**
 * A count as it appears inside a receipt.
 */
final readonly class ReceivedLineResource
{
    public function __construct(
        public int $lineNumber,
        public string $productId,
        public int $units,
        public ?string $lot,
        public ?string $expiryDate,
        public ItemCondition $condition,
        public string $receivedAt,
    ) {
    }

    public static function fromDomain(ReceivedLine $line): self
    {
        return new self(
            $line->lineNumber,
            $line->productId->value,
            $line->units,
            $line->lot?->value,
            $line->expiryDate?->format('Y-m-d'),
            $line->condition,
            $line->receivedAt->format(\DATE_ATOM),
        );
    }
}
