<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Inventory\Domain\Exception\InvalidReservationReference;

/**
 * What a reservation serves, in the words of the context that asked for it: an order line. It is
 * an opaque text to Inventory, which never looks inside it (ADR 0003). One reference has at most
 * one reservation, so asking again for the same one tops it up and does not reserve twice.
 */
final readonly class ReservationReference implements \Stringable
{
    private const int MAX_LENGTH = 80;

    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);
        $length = mb_strlen($trimmed);

        if ($length < 1 || $length > self::MAX_LENGTH || 1 === preg_match('/\p{Cc}/u', $trimmed)) {
            throw InvalidReservationReference::forValue($value);
        }

        return new self($trimmed);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
