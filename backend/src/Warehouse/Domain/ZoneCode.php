<?php

declare(strict_types=1);

namespace App\Warehouse\Domain;

use App\Warehouse\Domain\Exception\InvalidZoneCode;

/**
 * Short code of a zone, for example `FAST` or `CHILL`. Unique within its warehouse (WH-02).
 */
final readonly class ZoneCode implements \Stringable
{
    private const string PATTERN = '/^[A-Z0-9]{1,10}$/';

    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $normalized = strtoupper(trim($value));

        if (1 !== preg_match(self::PATTERN, $normalized)) {
            throw InvalidZoneCode::forValue($value);
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
