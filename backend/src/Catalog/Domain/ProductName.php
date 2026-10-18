<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Catalog\Domain\Exception\InvalidProductName;

/**
 * The description a person reads on screen. It is typed by users, so it is stored as
 * given and never translated.
 */
final readonly class ProductName implements \Stringable
{
    private const int MAX_LENGTH = 150;

    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);
        $length = mb_strlen($trimmed);

        if ($length < 1 || $length > self::MAX_LENGTH || 1 === preg_match('/\p{Cc}/u', $trimmed)) {
            throw InvalidProductName::forValue($value);
        }

        return new self($trimmed);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
