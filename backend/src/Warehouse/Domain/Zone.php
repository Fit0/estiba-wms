<?php

declare(strict_types=1);

namespace App\Warehouse\Domain;

use App\Shared\Domain\ProductFamily;
use App\Shared\Domain\StorageClass;

/**
 * A named area of a warehouse with one storage class, one place in the picking route and the
 * product families it keeps, if any (putaway by family puts a product in a zone of its family).
 * A warehouse has few zones, so zones live inside the {@see Warehouse} aggregate.
 *
 * A zone never changes in place: the warehouse replaces it.
 */
final readonly class Zone
{
    /** @var list<ProductFamily> */
    public array $families;

    /**
     * @param list<ProductFamily> $families
     */
    public function __construct(
        public ZoneId $id,
        public ZoneCode $code,
        public DisplayName $name,
        public StorageClass $storageClass,
        public PickSequence $pickSequence,
        array $families = [],
    ) {
        $unique = [];
        foreach ($families as $family) {
            $unique[$family->value] = $family;
        }
        ksort($unique);

        $this->families = array_values($unique);
    }

    /**
     * @param list<ProductFamily> $families
     */
    public function withFamilies(array $families): self
    {
        return new self($this->id, $this->code, $this->name, $this->storageClass, $this->pickSequence, $families);
    }

    public function keeps(ProductFamily $family): bool
    {
        return array_any($this->families, static fn (ProductFamily $own): bool => $own->equals($family));
    }
}
