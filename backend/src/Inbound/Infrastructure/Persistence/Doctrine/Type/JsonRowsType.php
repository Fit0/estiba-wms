<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Persistence\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

/**
 * Stores a list of small value objects of an aggregate as a JSON list in its row, the way the
 * zones of a warehouse are stored (ADR 0005). The lines are always read and written with their
 * aggregate, and Doctrine can only fill a relation with its own collection class, which the
 * domain must not import.
 *
 * The value objects never change in place: the aggregate replaces them, so Doctrine notices the
 * new list and writes it.
 *
 * @template T of object
 */
abstract class JsonRowsType extends Type
{
    protected const string DATE_FORMAT = 'Y-m-d';

    protected const string MOMENT_FORMAT = 'Y-m-d\TH:i:s.uP';

    /**
     * @return class-string<T>
     */
    abstract protected function itemClass(): string;

    /**
     * @param T $item
     *
     * @return array<string, bool|int|string|null>
     */
    abstract protected function toRow(object $item): array;

    /**
     * @param array<array-key, mixed> $row
     *
     * @return T|null null when the row does not have the expected shape
     */
    abstract protected function fromRow(array $row): ?object;

    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    #[\Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string
    {
        if (!\is_array($value)) {
            throw InvalidType::new($value, static::class, ['array']);
        }

        $class = $this->itemClass();
        $rows = [];
        foreach ($value as $item) {
            if (!$item instanceof $class) {
                throw InvalidType::new($item, static::class, [$class]);
            }

            $rows[] = $this->toRow($item);
        }

        return json_encode($rows, \JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<T>
     */
    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): array
    {
        if (!\is_string($value)) {
            throw ValueNotConvertible::new(get_debug_type($value), static::class);
        }

        $rows = json_decode($value, true, 8, \JSON_THROW_ON_ERROR);
        if (!\is_array($rows)) {
            throw ValueNotConvertible::new($value, static::class);
        }

        $items = [];
        foreach ($rows as $row) {
            $item = \is_array($row) ? $this->fromRow($row) : null;
            if (null === $item) {
                throw ValueNotConvertible::new($value, static::class);
            }

            $items[] = $item;
        }

        return $items;
    }

    protected static function formatDate(?\DateTimeImmutable $date): ?string
    {
        return $date?->format(self::DATE_FORMAT);
    }

    /**
     * @return \DateTimeImmutable|false|null false when the text is not a date
     */
    protected static function parseDate(mixed $text): \DateTimeImmutable|false|null
    {
        if (null === $text) {
            return null;
        }

        return \is_string($text) ? \DateTimeImmutable::createFromFormat('!'.self::DATE_FORMAT, $text) : false;
    }
}
