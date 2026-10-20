<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Identity\Domain\Exception\InvalidTenantName;

/**
 * The name of the company a tenant stands for. Typed by users, so stored as given.
 */
final readonly class TenantName implements \Stringable
{
    private const int MAX_LENGTH = 100;

    private function __construct(public string $value)
    {
    }

    /**
     * @throws InvalidTenantName IDN-07
     */
    public static function fromString(string $value): self
    {
        $trimmed = trim($value);
        $length = mb_strlen($trimmed);

        if ($length < 1 || $length > self::MAX_LENGTH || 1 === preg_match('/\p{Cc}/u', $trimmed)) {
            throw InvalidTenantName::forValue($value);
        }

        return new self($trimmed);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
