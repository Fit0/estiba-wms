<?php

declare(strict_types=1);

namespace App\Warehouse\Domain;

use App\Warehouse\Domain\Exception\InvalidPickSequence;

/**
 * Position of a zone or a location along the picking route. Lower numbers come first.
 */
final readonly class PickSequence
{
    private function __construct(public int $value)
    {
    }

    public static function of(int $value): self
    {
        if ($value < 0) {
            throw InvalidPickSequence::forValue($value);
        }

        return new self($value);
    }
}
