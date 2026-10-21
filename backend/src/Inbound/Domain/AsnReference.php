<?php

declare(strict_types=1);

namespace App\Inbound\Domain;

use App\Inbound\Domain\Exception\InvalidAsnReference;

/**
 * The number the supplier or the ERP gives the ASN, such as a delivery note number. It is unique
 * per tenant (INB-03), so importing the same ASN twice is noticed. Stored as given.
 */
final readonly class AsnReference implements \Stringable
{
    public const int MAX_LENGTH = 40;

    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);
        $length = mb_strlen($trimmed);

        if ($length < 1 || $length > self::MAX_LENGTH || 1 === preg_match('/\p{Cc}/u', $trimmed)) {
            throw InvalidAsnReference::forValue($value);
        }

        return new self($trimmed);
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
