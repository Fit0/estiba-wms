<?php

declare(strict_types=1);

namespace App\Warehouse\Domain;

use App\Warehouse\Domain\Exception\InvalidLocationCode;

/**
 * Where a location is, written `aisle-module-level`: `A-03-02` is aisle A, module 03, level 02.
 * Unique within its warehouse (WH-03).
 */
final readonly class LocationCode implements \Stringable
{
    private const string PATTERN = '/^(?<aisle>[A-Z]{1,2})-(?<module>\d{2,3})-(?<level>\d{2})$/';

    private function __construct(
        public string $value,
        public string $aisle,
        public string $module,
        public string $level,
    ) {
    }

    public static function fromString(string $value): self
    {
        $normalized = strtoupper(trim($value));

        if (1 !== preg_match(self::PATTERN, $normalized, $parts)) {
            throw InvalidLocationCode::forValue($value);
        }

        return new self($normalized, $parts['aisle'], $parts['module'], $parts['level']);
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
