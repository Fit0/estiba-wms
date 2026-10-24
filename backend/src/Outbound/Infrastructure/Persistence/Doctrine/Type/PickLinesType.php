<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Persistence\Doctrine\Type;

use App\Outbound\Domain\OrderId;
use App\Outbound\Domain\PickLine;
use App\Outbound\Domain\ProductId;
use App\Outbound\Domain\ShortPickReason;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\JsonRowsType;

/**
 * The stops of a pick list, in the order of the walk.
 *
 * @extends JsonRowsType<PickLine>
 */
final class PickLinesType extends JsonRowsType
{
    public const string NAME = 'outbound_pick_lines';

    #[\Override]
    protected function itemClass(): string
    {
        return PickLine::class;
    }

    #[\Override]
    protected function toRow(object $item): array
    {
        return [
            'position' => $item->position,
            'order_id' => $item->orderId->value,
            'order_line_number' => $item->orderLineNumber,
            'product_id' => $item->productId->value,
            'stock_item_id' => $item->stockItemId,
            'location_id' => $item->locationId,
            'location_code' => $item->locationCode,
            'lot' => $item->lot,
            'expiry_date' => self::formatDate($item->expiryDate),
            'quantity_to_pick' => $item->quantityToPick,
            'quantity_picked' => $item->quantityPicked,
            'confirmed' => $item->confirmed,
            'short_reason' => $item->shortReason?->value,
        ];
    }

    #[\Override]
    protected function fromRow(array $row): ?PickLine
    {
        $lot = $row['lot'] ?? null;
        $shortReason = $row['short_reason'] ?? null;
        $expiryDate = self::parseDate($row['expiry_date'] ?? null);

        if (!\is_int($row['position'] ?? null)
            || !\is_string($row['order_id'] ?? null)
            || !\is_int($row['order_line_number'] ?? null)
            || !\is_string($row['product_id'] ?? null)
            || !\is_string($row['stock_item_id'] ?? null)
            || !\is_string($row['location_id'] ?? null)
            || !\is_string($row['location_code'] ?? null)
            || (null !== $lot && !\is_string($lot))
            || false === $expiryDate
            || !\is_int($row['quantity_to_pick'] ?? null)
            || !\is_int($row['quantity_picked'] ?? null)
            || !\is_bool($row['confirmed'] ?? null)
            || (null !== $shortReason && !\is_string($shortReason))
        ) {
            return null;
        }

        return new PickLine(
            $row['position'],
            OrderId::fromString($row['order_id']),
            $row['order_line_number'],
            ProductId::fromString($row['product_id']),
            $row['stock_item_id'],
            $row['location_id'],
            $row['location_code'],
            $lot,
            $expiryDate,
            $row['quantity_to_pick'],
            $row['quantity_picked'],
            $row['confirmed'],
            null === $shortReason ? null : ShortPickReason::fromString($shortReason),
        );
    }
}
