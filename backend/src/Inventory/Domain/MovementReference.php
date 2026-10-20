<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

/**
 * What a movement came from, in the words of the process that caused it: a delivery note, an
 * order number, a count sheet. It is a free reference, not a link to another context.
 */
final readonly class MovementReference implements \Stringable
{
    private const int MAX_LENGTH = 80;

    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        return new self(MovementText::clean('reference', $value, self::MAX_LENGTH));
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
