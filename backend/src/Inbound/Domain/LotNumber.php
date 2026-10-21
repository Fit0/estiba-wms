<?php

declare(strict_types=1);

namespace App\Inbound\Domain;

use App\Inbound\Domain\Exception\InvalidLotNumber;

/**
 * The code of a production batch, as printed by the manufacturer and read at the dock. Stored as
 * given, with the same rules as the lot of a stock item, so it can be booked into stock as it is.
 */
final readonly class LotNumber implements \Stringable
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
            throw InvalidLotNumber::forValue($value);
        }

        return new self($trimmed);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
