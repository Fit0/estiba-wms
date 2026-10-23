<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Persistence\Doctrine\Type;

use App\Inbound\Domain\AsnLine;
use App\Inbound\Domain\LotNumber;
use App\Inbound\Domain\ProductId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\JsonRowsType;

/**
 * The lines of an ASN.
 *
 * @extends JsonRowsType<AsnLine>
 */
final class AsnLinesType extends JsonRowsType
{
    public const string NAME = 'inbound_asn_lines';

    #[\Override]
    protected function itemClass(): string
    {
        return AsnLine::class;
    }

    #[\Override]
    protected function toRow(object $item): array
    {
        return [
            'product_id' => $item->productId->value,
            'expected_units' => $item->expectedUnits,
            'received_units' => $item->receivedUnits,
            'lot' => $item->lot?->value,
            'expiry_date' => self::formatDate($item->expiryDate),
        ];
    }

    #[\Override]
    protected function fromRow(array $row): ?AsnLine
    {
        $lot = $row['lot'] ?? null;
        $expiryDate = self::parseDate($row['expiry_date'] ?? null);

        if (!\is_string($row['product_id'] ?? null)
            || !\is_int($row['expected_units'] ?? null)
            || !\is_int($row['received_units'] ?? null)
            || (null !== $lot && !\is_string($lot))
            || false === $expiryDate
        ) {
            return null;
        }

        return new AsnLine(
            ProductId::fromString($row['product_id']),
            $row['expected_units'],
            $row['received_units'],
            null === $lot ? null : LotNumber::fromString($lot),
            $expiryDate,
        );
    }
}
