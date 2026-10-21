<?php

declare(strict_types=1);

namespace App\Inbound\Domain;

use App\Inbound\Domain\Exception\InvalidOverrideReason;

/**
 * Why an operator put goods somewhere other than the suggested location (INB-13): the location was
 * full, damaged, out of reach. Typed by users, so stored as given and never translated.
 */
final readonly class OverrideReason implements \Stringable
{
    public const int MAX_LENGTH = 200;

    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);
        $length = mb_strlen($trimmed);

        if ($length < 1 || $length > self::MAX_LENGTH || 1 === preg_match('/\p{Cc}/u', $trimmed)) {
            throw InvalidOverrideReason::forValue($value);
        }

        return new self($trimmed);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
