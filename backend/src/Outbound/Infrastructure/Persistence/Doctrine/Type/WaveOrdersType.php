<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Persistence\Doctrine\Type;

use App\Outbound\Domain\OrderId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\JsonRowsType;

/**
 * The orders of a wave, by identifier.
 *
 * @extends JsonRowsType<OrderId>
 */
final class WaveOrdersType extends JsonRowsType
{
    public const string NAME = 'outbound_wave_orders';

    #[\Override]
    protected function itemClass(): string
    {
        return OrderId::class;
    }

    #[\Override]
    protected function toRow(object $item): array
    {
        return ['order_id' => $item->value];
    }

    #[\Override]
    protected function fromRow(array $row): ?OrderId
    {
        return \is_string($row['order_id'] ?? null) ? OrderId::fromString($row['order_id']) : null;
    }
}
