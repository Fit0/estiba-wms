<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Http\Resource;

use App\Inbound\Domain\AsnLine;

/**
 * A line as it appears inside an ASN. Dates are days, as `YYYY-MM-DD`.
 */
final readonly class AsnLineResource
{
    public function __construct(
        public string $productId,
        public int $expectedUnits,
        public int $receivedUnits,
        public ?string $lot,
        public ?string $expiryDate,
    ) {
    }

    public static function fromDomain(AsnLine $line): self
    {
        return new self(
            $line->productId->value,
            $line->expectedUnits,
            $line->receivedUnits,
            $line->lot?->value,
            $line->expiryDate?->format('Y-m-d'),
        );
    }
}
