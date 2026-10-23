<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Persistence\Doctrine\Type;

use App\Outbound\Domain\PickListId;
use App\Shared\Infrastructure\Persistence\Doctrine\Type\JsonRowsType;

/**
 * The pick lists a wave was released into, by identifier.
 *
 * @extends JsonRowsType<PickListId>
 */
final class WavePickListsType extends JsonRowsType
{
    public const string NAME = 'outbound_wave_pick_lists';

    #[\Override]
    protected function itemClass(): string
    {
        return PickListId::class;
    }

    #[\Override]
    protected function toRow(object $item): array
    {
        return ['pick_list_id' => $item->value];
    }

    #[\Override]
    protected function fromRow(array $row): ?PickListId
    {
        return \is_string($row['pick_list_id'] ?? null) ? PickListId::fromString($row['pick_list_id']) : null;
    }
}
