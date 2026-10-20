<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

/**
 * Why stock was corrected: a count, a loss, a breakage. An adjustment cannot exist without one (STK-09).
 */
final readonly class AdjustmentReason implements \Stringable
{
    private const int MAX_LENGTH = 200;

    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        return new self(MovementText::clean('reason', $value, self::MAX_LENGTH));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
