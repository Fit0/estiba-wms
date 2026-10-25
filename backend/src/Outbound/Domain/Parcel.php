<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

/**
 * One physical package, closed: what it holds, what it weighs and the SSCC on its label. A parcel is
 * never open in the system, since the SSCC is assigned when it closes (OUT-19).
 */
final readonly class Parcel
{
    /**
     * @param list<ParcelContent> $contents
     */
    public function __construct(
        public Sscc $sscc,
        public array $contents,
        public int $weightGrams,
        public \DateTimeImmutable $closedAt,
    ) {
    }

    public function totalUnits(): int
    {
        return array_sum(array_map(static fn (ParcelContent $content): int => $content->units, $this->contents));
    }
}
