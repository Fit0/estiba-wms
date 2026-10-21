<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Persistence\Doctrine\Type;

use App\Inbound\Domain\ItemCondition;
use App\Inbound\Domain\LotNumber;
use App\Inbound\Domain\ProductId;
use App\Inbound\Domain\ReceivedLine;

/**
 * The counts of a receipt, in the order they were made.
 *
 * @extends JsonRowsType<ReceivedLine>
 */
final class ReceivedLinesType extends JsonRowsType
{
    public const string NAME = 'inbound_received_lines';

    #[\Override]
    protected function itemClass(): string
    {
        return ReceivedLine::class;
    }

    #[\Override]
    protected function toRow(object $item): array
    {
        return [
            'line_number' => $item->lineNumber,
            'product_id' => $item->productId->value,
            'units' => $item->units,
            'lot' => $item->lot?->value,
            'expiry_date' => self::formatDate($item->expiryDate),
            'condition' => $item->condition->value,
            'received_at' => $item->receivedAt->format(self::MOMENT_FORMAT),
        ];
    }

    #[\Override]
    protected function fromRow(array $row): ?ReceivedLine
    {
        $lot = $row['lot'] ?? null;
        $expiryDate = self::parseDate($row['expiry_date'] ?? null);
        $condition = \is_string($row['condition'] ?? null) ? ItemCondition::tryFrom($row['condition']) : null;
        $receivedAt = \is_string($row['received_at'] ?? null) ? \DateTimeImmutable::createFromFormat(self::MOMENT_FORMAT, $row['received_at']) : false;

        if (!\is_int($row['line_number'] ?? null)
            || !\is_string($row['product_id'] ?? null)
            || !\is_int($row['units'] ?? null)
            || (null !== $lot && !\is_string($lot))
            || false === $expiryDate
            || null === $condition
            || false === $receivedAt
        ) {
            return null;
        }

        return new ReceivedLine(
            $row['line_number'],
            ProductId::fromString($row['product_id']),
            $row['units'],
            null === $lot ? null : LotNumber::fromString($lot),
            $expiryDate,
            $condition,
            $receivedAt,
        );
    }
}
