<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Catalog\Domain\Exception\InvalidDimensions;

/**
 * The size and weight of one base unit, in millimetres and grams. Whole numbers keep
 * the arithmetic exact; every value is positive (CAT-09).
 */
final readonly class Dimensions
{
    private function __construct(
        public int $lengthMm,
        public int $widthMm,
        public int $heightMm,
        public int $weightGrams,
    ) {
    }

    public static function of(int $lengthMm, int $widthMm, int $heightMm, int $weightGrams): self
    {
        foreach (['lengthMm' => $lengthMm, 'widthMm' => $widthMm, 'heightMm' => $heightMm, 'weightGrams' => $weightGrams] as $field => $value) {
            if ($value < 1) {
                throw InvalidDimensions::forField($field, $value);
            }
        }

        return new self($lengthMm, $widthMm, $heightMm, $weightGrams);
    }

    public function volumeCubicMm(): int
    {
        return $this->lengthMm * $this->widthMm * $this->heightMm;
    }
}
