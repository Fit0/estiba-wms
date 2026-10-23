<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

use App\Outbound\Domain\Exception\InvalidOrderReference;

/**
 * The number the customer's system gave the order. It is unique per tenant, so importing the
 * same order twice changes nothing (OUT-01). Kept as given, apart from the surrounding spaces.
 */
final readonly class OrderReference implements \Stringable
{
    public const int MAX_LENGTH = 40;

    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);
        $length = mb_strlen($trimmed);

        if ($length < 1 || $length > self::MAX_LENGTH || 1 === preg_match('/\p{Cc}/u', $trimmed)) {
            throw InvalidOrderReference::forValue($value);
        }

        return new self($trimmed);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
