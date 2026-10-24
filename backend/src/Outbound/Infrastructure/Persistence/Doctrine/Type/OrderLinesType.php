<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Persistence\Doctrine\Type;

use App\Outbound\Domain\OrderLine;
use App\Outbound\Domain\ProductId;
use App\Outbound\Domain\ReservationId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\JsonRowsType;

/**
 * The lines of an order.
 *
 * @extends JsonRowsType<OrderLine>
 */
final class OrderLinesType extends JsonRowsType
{
    public const string NAME = 'outbound_order_lines';

    #[\Override]
    protected function itemClass(): string
    {
        return OrderLine::class;
    }

    #[\Override]
    protected function toRow(object $item): array
    {
        return [
            'line_number' => $item->lineNumber,
            'product_id' => $item->productId->value,
            'ordered_units' => $item->orderedUnits,
            'allocated_units' => $item->allocatedUnits,
            'reservation_id' => $item->reservationId?->value,
            'picked_units' => $item->pickedUnits,
        ];
    }

    #[\Override]
    protected function fromRow(array $row): ?OrderLine
    {
        $reservationId = $row['reservation_id'] ?? null;
        $picked = $row['picked_units'] ?? 0;

        if (!\is_int($row['line_number'] ?? null)
            || !\is_string($row['product_id'] ?? null)
            || !\is_int($row['ordered_units'] ?? null)
            || !\is_int($row['allocated_units'] ?? null)
            || (null !== $reservationId && !\is_string($reservationId))
            || !\is_int($picked)
        ) {
            return null;
        }

        return new OrderLine(
            $row['line_number'],
            ProductId::fromString($row['product_id']),
            $row['ordered_units'],
            $row['allocated_units'],
            null === $reservationId ? null : ReservationId::fromString($reservationId),
            $picked,
        );
    }
}
