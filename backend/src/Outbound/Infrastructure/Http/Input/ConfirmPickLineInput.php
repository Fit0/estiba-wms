<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\Input;

/**
 * What the operator scanned and counted: the label of the location, the barcode of the product, its
 * lot when the line has one, and how many units were picked. A reason is required when fewer than asked.
 */
final readonly class ConfirmPickLineInput
{
    public function __construct(
        public string $location,
        public string $gtin,
        public int $units,
        public ?string $lot = null,
        public ?string $reason = null,
    ) {
    }
}
