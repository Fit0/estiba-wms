<?php

declare(strict_types=1);

namespace App\Inbound\Domain;

use App\Inbound\Domain\Exception\InvalidSupplierName;

/**
 * Who sends the goods. Typed by users or imported from the ERP, so stored as given and never
 * translated.
 */
final readonly class SupplierName implements \Stringable
{
    public const int MAX_LENGTH = 100;

    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);
        $length = mb_strlen($trimmed);

        if ($length < 1 || $length > self::MAX_LENGTH || 1 === preg_match('/\p{Cc}/u', $trimmed)) {
            throw InvalidSupplierName::forValue($value);
        }

        return new self($trimmed);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
