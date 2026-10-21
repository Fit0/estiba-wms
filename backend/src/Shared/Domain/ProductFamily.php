<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * A logistic family of products that are stored together, such as shirts, trousers or shoes in a
 * fashion warehouse. Catalog gives a product its family and Warehouse gives a zone the families it
 * keeps, so the family lives in the shared kernel, like {@see StorageClass}.
 *
 * A code of 1 to 20 letters or digits, with single hyphens between groups. Case does not matter.
 */
final readonly class ProductFamily implements \Stringable
{
    private const string PATTERN = '/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/';

    private const int MAX_LENGTH = 20;

    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $normalized = strtoupper(trim($value));

        if (\strlen($normalized) > self::MAX_LENGTH || 1 !== preg_match(self::PATTERN, $normalized)) {
            throw InvalidProductFamily::forValue($value);
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
