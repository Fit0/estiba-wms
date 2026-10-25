<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * The check digit that ends every GS1 number (GTIN, SSCC…): from the right, the digits weigh
 * 3, 1, 3, 1… and the check digit completes the sum to a multiple of ten.
 */
final readonly class Gs1CheckDigit
{
    public static function for(string $digitsWithoutCheckDigit): int
    {
        $sum = 0;
        $weight = 3;

        foreach (array_reverse(str_split($digitsWithoutCheckDigit)) as $digit) {
            $sum += $weight * (int) $digit;
            $weight = 4 - $weight;
        }

        return (10 - $sum % 10) % 10;
    }
}
