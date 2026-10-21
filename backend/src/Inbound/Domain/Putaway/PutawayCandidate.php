<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Putaway;

use App\Inbound\Domain\LocationId;
use App\Shared\Domain\ProductFamily;

/**
 * A location the goods could go to: active, of a type that stores them and in a zone of their
 * storage class (INB-11). The strategy only learns whether it is empty, whether the units fit, and
 * the families of its zone. Candidates come in the order of the picking route.
 */
final readonly class PutawayCandidate
{
    /**
     * @param list<ProductFamily> $families
     */
    public function __construct(
        public LocationId $locationId,
        public string $code,
        public array $families,
        public bool $empty,
        public bool $hasRoom,
    ) {
    }

    /**
     * Empty, with room for the units: where a strategy may send the goods.
     */
    public function isFree(): bool
    {
        return $this->empty && $this->hasRoom;
    }

    public function keeps(ProductFamily $family): bool
    {
        return array_any($this->families, static fn (ProductFamily $own): bool => $own->equals($family));
    }
}
