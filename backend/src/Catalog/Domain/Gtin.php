<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Catalog\Domain\Exception\InvalidGtin;
use App\Shared\Domain\Gs1CheckDigit;

/**
 * A GS1 barcode number: GTIN-8, GTIN-12 (UPC), GTIN-13 (EAN) or GTIN-14, whose last digit
 * is a check digit (CAT-02).
 *
 * The same item can be printed with different lengths (a UPC is an EAN with a leading
 * zero), so two GTINs are equal when they match once padded to 14 digits.
 */
final readonly class Gtin implements \Stringable
{
    private const array LENGTHS = [8, 12, 13, 14];

    private function __construct(
        public string $value,
        public string $normalized,
    ) {
    }

    public static function fromString(string $value): self
    {
        $digits = trim($value);

        if (!\in_array(\strlen($digits), self::LENGTHS, true)
            || 1 !== preg_match('/^\d+$/', $digits)
            || self::checkDigitFor(substr($digits, 0, -1)) !== (int) substr($digits, -1)
        ) {
            throw InvalidGtin::forValue($value);
        }

        return new self($digits, str_pad($digits, 14, '0', \STR_PAD_LEFT));
    }

    /**
     * The GS1 check digit of a number without it: from the right, digits weigh 3, 1, 3, 1…
     * and the check digit completes the sum to a multiple of ten.
     */
    public static function checkDigitFor(string $digitsWithoutCheckDigit): int
    {
        return Gs1CheckDigit::for($digitsWithoutCheckDigit);
    }

    public function equals(self $other): bool
    {
        return $this->normalized === $other->normalized;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
