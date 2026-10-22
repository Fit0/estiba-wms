<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\ReadModel;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

/**
 * Reads the columns of a row fetched with plain SQL, checking each one has the type it should.
 */
final readonly class Row
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(
        private array $values,
        private AbstractPlatform $platform,
    ) {
    }

    public function string(string $column): string
    {
        $value = $this->values[$column] ?? null;
        if (!\is_string($value)) {
            throw new \UnexpectedValueException(\sprintf('Column %s should hold text.', $column));
        }

        return $value;
    }

    public function nullableString(string $column): ?string
    {
        return null === ($this->values[$column] ?? null) ? null : $this->string($column);
    }

    public function int(string $column): int
    {
        $value = $this->values[$column] ?? null;
        if (\is_int($value)) {
            return $value;
        }
        if (!\is_string($value) || 1 !== preg_match('/^-?\d+$/', $value)) {
            throw new \UnexpectedValueException(\sprintf('Column %s should hold a whole number.', $column));
        }

        return (int) $value;
    }

    public function nullableDate(string $column): ?\DateTimeImmutable
    {
        return null === ($this->values[$column] ?? null) ? null : $this->convert($column, Types::DATE_IMMUTABLE);
    }

    public function dateTime(string $column): \DateTimeImmutable
    {
        return $this->convert($column, Types::DATETIME_IMMUTABLE);
    }

    private function convert(string $column, string $type): \DateTimeImmutable
    {
        $value = Type::getType($type)->convertToPHPValue($this->values[$column] ?? null, $this->platform);
        if (!$value instanceof \DateTimeImmutable) {
            throw new \UnexpectedValueException(\sprintf('Column %s should hold a date.', $column));
        }

        return $value;
    }
}
