<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Inventory\Domain\Exception\InvalidQuantity;
use App\Inventory\Domain\Exception\NegativeStockNotAllowed;

/**
 * A whole number of base units that is zero or more (STK-01). A negative quantity cannot exist,
 * so no code path can build negative stock by accident.
 */
final readonly class Quantity
{
    private function __construct(public int $units)
    {
    }

    /**
     * @throws InvalidQuantity STK-01
     */
    public static function of(int $units): self
    {
        if ($units < 0) {
            throw InvalidQuantity::forUnits($units);
        }

        return new self($units);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function plus(self $other): self
    {
        return new self($this->units + $other->units);
    }

    /**
     * @throws NegativeStockNotAllowed STK-01
     */
    public function minus(self $other): self
    {
        if ($other->units > $this->units) {
            throw NegativeStockNotAllowed::taking($other->units, $this->units);
        }

        return new self($this->units - $other->units);
    }

    public function isZero(): bool
    {
        return 0 === $this->units;
    }

    public function equals(self $other): bool
    {
        return $this->units === $other->units;
    }
}
