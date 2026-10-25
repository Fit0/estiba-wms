<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Persistence\Doctrine\Type;

use App\Outbound\Domain\ParcelContent;
use App\Outbound\Domain\ProductId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\JsonRowsType;

/**
 * What an order had picked when packing began, by product.
 *
 * @extends JsonRowsType<ParcelContent>
 */
final class ShipmentPickedType extends JsonRowsType
{
    public const string NAME = 'outbound_shipment_picked';

    #[\Override]
    protected function itemClass(): string
    {
        return ParcelContent::class;
    }

    #[\Override]
    protected function toRow(object $item): array
    {
        return ['product_id' => $item->productId->value, 'units' => $item->units];
    }

    #[\Override]
    protected function fromRow(array $row): ?ParcelContent
    {
        if (!\is_string($row['product_id'] ?? null) || !\is_int($row['units'] ?? null)) {
            return null;
        }

        return new ParcelContent(ProductId::fromString($row['product_id']), $row['units']);
    }
}
