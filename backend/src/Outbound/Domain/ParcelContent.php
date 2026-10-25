<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

use App\Outbound\Domain\Exception\InvalidParcelUnits;

/**
 * Units of one product, in a parcel or picked for an order.
 */
final readonly class ParcelContent
{
    /**
     * @throws InvalidParcelUnits when the units are not a positive number
     */
    public function __construct(
        public ProductId $productId,
        public int $units,
    ) {
        if ($units < 1) {
            throw InvalidParcelUnits::of($productId->value, $units);
        }
    }

    public function plus(int $units): self
    {
        return new self($this->productId, $this->units + $units);
    }
}
