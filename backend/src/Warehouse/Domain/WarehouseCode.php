<?php

declare(strict_types=1);

namespace App\Warehouse\Domain;

use App\Warehouse\Domain\Exception\InvalidWarehouseCode;

/**
 * Short code staff use for a warehouse, for example `MAD1` or `BCN-NORTE`.
 * Unique per tenant (WH-01).
 */
final readonly class WarehouseCode implements \Stringable
{
    private const string PATTERN = '/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/';
    private const int MIN_LENGTH = 2;
    private const int MAX_LENGTH = 12;

    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $normalized = strtoupper(trim($value));
        $length = \strlen($normalized);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH || 1 !== preg_match(self::PATTERN, $normalized)) {
            throw InvalidWarehouseCode::forValue($value);
        }

        return new self($normalized);
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
