<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\Resource;

use App\Outbound\Domain\PickLine;

final readonly class PickLineResource
{
    public function __construct(
        public int $position,
        public string $orderId,
        public int $orderLineNumber,
        public string $productId,
        public string $locationId,
        public string $locationCode,
        public ?string $lot,
        public ?string $expiryDate,
        public int $quantityToPick,
        public int $quantityPicked,
        public bool $confirmed,
        public ?string $shortReason,
    ) {
    }

    public static function fromDomain(PickLine $line): self
    {
        return new self(
            $line->position,
            $line->orderId->value,
            $line->orderLineNumber,
            $line->productId->value,
            $line->locationId,
            $line->locationCode,
            $line->lot,
            $line->expiryDate?->format('Y-m-d'),
            $line->quantityToPick,
            $line->quantityPicked,
            $line->confirmed,
            $line->shortReason?->value,
        );
    }
}
