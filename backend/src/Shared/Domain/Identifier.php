<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Base of every aggregate identifier: a UUID kept as text.
 *
 * The domain never generates identifiers. The infrastructure creates a UUID v7 and
 * hands it in, so the domain stays free of any UUID library.
 */
abstract readonly class Identifier implements \Stringable
{
    private const string PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    final protected function __construct(public string $value)
    {
    }

    final public static function fromString(string $value): static
    {
        $normalized = strtolower(trim($value));

        if (1 !== preg_match(self::PATTERN, $normalized)) {
            throw InvalidIdentifier::forValue(static::class, $value);
        }

        return new static($normalized);
    }

    final public function equals(self $other): bool
    {
        return static::class === $other::class && $this->value === $other->value;
    }

    final public function __toString(): string
    {
        return $this->value;
    }
}
