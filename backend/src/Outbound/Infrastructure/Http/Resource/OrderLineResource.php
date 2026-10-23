<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\Resource;

use App\Outbound\Domain\OrderLine;

final readonly class OrderLineResource
{
    public function __construct(
        public int $lineNumber,
        public string $productId,
        public int $orderedUnits,
        public int $allocatedUnits,
        public int $shortageUnits,
        public ?string $reservationId,
    ) {
    }

    public static function fromDomain(OrderLine $line): self
    {
        return new self(
            $line->lineNumber,
            $line->productId->value,
            $line->orderedUnits,
            $line->allocatedUnits,
            $line->shortageUnits(),
            $line->reservationId?->value,
        );
    }
}
