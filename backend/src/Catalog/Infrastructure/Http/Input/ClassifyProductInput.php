<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http\Input;

use App\Shared\Domain\TurnoverClass;

/**
 * Replaces the classification: a family that is not sent is removed.
 */
final readonly class ClassifyProductInput
{
    public function __construct(
        public TurnoverClass $turnoverClass,
        public ?string $family = null,
    ) {
    }
}
