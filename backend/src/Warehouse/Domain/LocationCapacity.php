<?php

declare(strict_types=1);

namespace App\Warehouse\Domain;

use App\Warehouse\Domain\Exception\InvalidCapacity;

/**
 * How much a location can hold. A limit left out means the location has none of that kind:
 * a dock or a staging area usually declares no limits at all.
 * Every declared limit is a positive whole number (WH-04).
 */
final readonly class LocationCapacity
{
    private function __construct(
        public ?int $maxWeightKg,
        public ?int $maxVolumeLitres,
        public ?int $maxPalletPositions,
    ) {
    }

    public static function of(?int $maxWeightKg = null, ?int $maxVolumeLitres = null, ?int $maxPalletPositions = null): self
    {
        foreach (['maxWeightKg' => $maxWeightKg, 'maxVolumeLitres' => $maxVolumeLitres, 'maxPalletPositions' => $maxPalletPositions] as $field => $limit) {
            if (null !== $limit && $limit < 1) {
                throw InvalidCapacity::forField($field, $limit);
            }
        }

        return new self($maxWeightKg, $maxVolumeLitres, $maxPalletPositions);
    }

    public static function unlimited(): self
    {
        return new self(null, null, null);
    }

    public function hasLimits(): bool
    {
        return null !== $this->maxWeightKg || null !== $this->maxVolumeLitres || null !== $this->maxPalletPositions;
    }
}
