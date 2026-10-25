<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Persistence\Doctrine\Type;

use App\Outbound\Domain\Parcel;
use App\Outbound\Domain\ParcelContent;
use App\Outbound\Domain\ProductId;
use App\Outbound\Domain\Sscc;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\JsonRowsType;

/**
 * The closed parcels of a shipment, each with its contents.
 *
 * @extends JsonRowsType<Parcel>
 */
final class ParcelsType extends JsonRowsType
{
    public const string NAME = 'outbound_parcels';

    #[\Override]
    protected function itemClass(): string
    {
        return Parcel::class;
    }

    #[\Override]
    protected function toRow(object $item): array
    {
        return [
            'sscc' => $item->sscc->value,
            'weight_grams' => $item->weightGrams,
            'closed_at' => $item->closedAt->format(self::MOMENT_FORMAT),
            'contents' => array_map(
                static fn (ParcelContent $content): array => ['product_id' => $content->productId->value, 'units' => $content->units],
                $item->contents,
            ),
        ];
    }

    #[\Override]
    protected function fromRow(array $row): ?Parcel
    {
        $closedAt = \is_string($row['closed_at'] ?? null) ? \DateTimeImmutable::createFromFormat(self::MOMENT_FORMAT, $row['closed_at']) : false;
        $contents = $this->contentsFrom($row['contents'] ?? null);

        if (!\is_string($row['sscc'] ?? null) || !\is_int($row['weight_grams'] ?? null) || false === $closedAt || null === $contents) {
            return null;
        }

        return new Parcel(Sscc::fromString($row['sscc']), $contents, $row['weight_grams'], $closedAt);
    }

    /**
     * @return list<ParcelContent>|null null when the contents do not have the expected shape
     */
    private function contentsFrom(mixed $rows): ?array
    {
        if (!\is_array($rows)) {
            return null;
        }

        $contents = [];
        foreach ($rows as $row) {
            if (!\is_array($row) || !\is_string($row['product_id'] ?? null) || !\is_int($row['units'] ?? null)) {
                return null;
            }

            $contents[] = new ParcelContent(ProductId::fromString($row['product_id']), $row['units']);
        }

        return $contents;
    }
}
