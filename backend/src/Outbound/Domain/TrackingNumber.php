<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

use App\Outbound\Domain\Exception\InvalidTrackingNumber;

/**
 * What the carrier gave to follow the parcels on their way. Typed by users, so it is stored as given and never translated.
 */
final readonly class TrackingNumber implements \Stringable
{
    private const int MAX_LENGTH = 60;

    private function __construct(public string $value)
    {
    }

    /**
     * @throws InvalidTrackingNumber
     */
    public static function fromString(string $value): self
    {
        $trimmed = trim($value);
        $length = mb_strlen($trimmed);

        if ($length < 1 || $length > self::MAX_LENGTH || 1 === preg_match('/\p{Cc}/u', $trimmed)) {
            throw InvalidTrackingNumber::forValue($value);
        }

        return new self($trimmed);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
