<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http\Input;

/**
 * A merge patch: only the fields that are sent change.
 */
final readonly class UpdateProductInput
{
    public function __construct(
        public ?string $name = null,
        public ?int $lengthMm = null,
        public ?int $widthMm = null,
        public ?int $heightMm = null,
        public ?int $weightGrams = null,
    ) {
    }
}
