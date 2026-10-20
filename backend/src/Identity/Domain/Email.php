<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Identity\Domain\Exception\InvalidEmail;

/**
 * The address a user signs in with. Compared without regard to case, so it is kept in lower
 * case. Unique across the whole system (IDN-01).
 */
final readonly class Email implements \Stringable
{
    private const int MAX_LENGTH = 254;

    /**
     * @param non-empty-string $value
     */
    private function __construct(public string $value)
    {
    }

    /**
     * @throws InvalidEmail IDN-02
     */
    public static function fromString(string $value): self
    {
        $normalized = mb_strtolower(trim($value));

        if (\strlen($normalized) > self::MAX_LENGTH || false === filter_var($normalized, \FILTER_VALIDATE_EMAIL)) {
            throw InvalidEmail::forValue($value);
        }

        \assert('' !== $normalized, 'A valid address is never empty.');

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
