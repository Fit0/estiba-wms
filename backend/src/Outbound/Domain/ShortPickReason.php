<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

use App\Outbound\Domain\Exception\InvalidShortPickReason;

/**
 * Why an operator picked fewer units than the list asked for: the shelf was empty, the goods were
 * damaged, they were somewhere else. Typed on the floor, so it is stored as given.
 */
final readonly class ShortPickReason implements \Stringable
{
    private const int MAX_LENGTH = 200;

    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);
        $length = mb_strlen($trimmed);

        if ($length < 1 || $length > self::MAX_LENGTH || 1 === preg_match('/\p{Cc}/u', $trimmed)) {
            throw InvalidShortPickReason::forValue($value);
        }

        return new self($trimmed);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
