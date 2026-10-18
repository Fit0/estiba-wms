<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Catalog\Domain\Exception\InvalidSku;

/**
 * The tenant's own product code. Case and surrounding spaces do not matter, so `ab-1 `
 * and `AB-1` are the same SKU (CAT-01).
 */
final readonly class Sku implements \Stringable
{
    private const string PATTERN = '/^[A-Z0-9](?:[A-Z0-9._\/-]{0,38}[A-Z0-9])?$/';

    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $normalized = strtoupper(trim($value));

        if (1 !== preg_match(self::PATTERN, $normalized)) {
            throw InvalidSku::forValue($value);
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
