<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Persistence\Doctrine\Type;

use App\Inbound\Domain\ExpectedItem;
use App\Inbound\Domain\ProductId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\JsonRowsType;

/**
 * What a receipt expected of each product when it opened.
 *
 * @extends JsonRowsType<ExpectedItem>
 */
final class ExpectedItemsType extends JsonRowsType
{
    public const string NAME = 'inbound_expected_items';

    #[\Override]
    protected function itemClass(): string
    {
        return ExpectedItem::class;
    }

    #[\Override]
    protected function toRow(object $item): array
    {
        return ['product_id' => $item->productId->value, 'units' => $item->units];
    }

    #[\Override]
    protected function fromRow(array $row): ?ExpectedItem
    {
        if (!\is_string($row['product_id'] ?? null) || !\is_int($row['units'] ?? null)) {
            return null;
        }

        return new ExpectedItem(ProductId::fromString($row['product_id']), $row['units']);
    }
}
