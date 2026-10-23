<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

use App\Outbound\Domain\Exception\InvalidCustomerName;

/**
 * Who the order is for: a store, a franchise or a person. Typed by users, so it is stored as given
 * and never translated.
 */
final readonly class CustomerName implements \Stringable
{
    private const int MAX_LENGTH = 100;

    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);
        $length = mb_strlen($trimmed);

        if ($length < 1 || $length > self::MAX_LENGTH || 1 === preg_match('/\p{Cc}/u', $trimmed)) {
            throw InvalidCustomerName::forValue($value);
        }

        return new self($trimmed);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
