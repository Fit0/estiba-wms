<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine\Type;

use App\Inventory\Domain\Allocation;
use App\Inventory\Domain\Quantity;
use App\Inventory\Domain\StockItemId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\JsonRowsType;

/**
 * The stock items that cover a reservation, with their units.
 *
 * @extends JsonRowsType<Allocation>
 */
final class AllocationsType extends JsonRowsType
{
    public const string NAME = 'inventory_allocations';

    #[\Override]
    protected function itemClass(): string
    {
        return Allocation::class;
    }

    #[\Override]
    protected function toRow(object $item): array
    {
        return [
            'stock_item_id' => $item->stockItemId->value,
            'units' => $item->quantity->units,
        ];
    }

    #[\Override]
    protected function fromRow(array $row): ?Allocation
    {
        if (!\is_string($row['stock_item_id'] ?? null) || !\is_int($row['units'] ?? null)) {
            return null;
        }

        return new Allocation(StockItemId::fromString($row['stock_item_id']), Quantity::of($row['units']));
    }
}
