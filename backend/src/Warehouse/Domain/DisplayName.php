<?php

declare(strict_types=1);

namespace App\Warehouse\Domain;

use App\Warehouse\Domain\Exception\InvalidDisplayName;

/**
 * The name a person reads on screen, such as "Madrid central warehouse".
 * It is typed by users, so it is stored as given and never translated.
 */
final readonly class DisplayName implements \Stringable
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
            throw InvalidDisplayName::forValue($value);
        }

        return new self($trimmed);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
