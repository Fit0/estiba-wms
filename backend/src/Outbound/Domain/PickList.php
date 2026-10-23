<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

use App\Outbound\Domain\Event\PickListGenerated;
use App\Outbound\Domain\Exception\InvalidPickList;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\TenantId;

/**
 * The stops one operator walks, already in the order of the route. The list is made when a wave is
 * released, from the stock that was reserved for its orders, and it asks for exactly those units.
 *
 * Starting it, assigning an operator and confirming picks arrive with picking (OUT-10 to OUT-13).
 */
final class PickList extends AggregateRoot
{
    private PickListStatus $status = PickListStatus::Generated;

    /**
     * @param non-empty-list<PickLine> $lines
     */
    private function __construct(
        private readonly PickListId $id,
        private readonly TenantId $tenantId,
        private readonly WaveId $waveId,
        private readonly array $lines,
        private readonly \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param list<PickRequest> $route the picks of this list, in the order of the walk
     *
     * @throws InvalidPickList when there is nothing to pick, or a stop asks for no units
     */
    public static function generate(PickListId $id, TenantId $tenantId, WaveId $waveId, array $route, \DateTimeImmutable $at): self
    {
        $lines = [];
        foreach ($route as $index => $request) {
            if ($request->units < 1) {
                throw InvalidPickList::noUnits($index + 1);
            }

            $lines[] = PickLine::at($index + 1, $request);
        }

        if ([] === $lines) {
            throw InvalidPickList::noLines();
        }

        $pickList = new self($id, $tenantId, $waveId, $lines, $at);
        $pickList->record(new PickListGenerated($id, $waveId, $tenantId, $at));

        return $pickList;
    }

    /**
     * Calls the list off, because its wave was. Doing it again changes nothing.
     */
    public function cancel(): void
    {
        $this->status = PickListStatus::Cancelled;
    }

    /**
     * The units the list asks for, in all its stops.
     */
    public function unitsToPick(): int
    {
        return array_sum(array_map(static fn (PickLine $line): int => $line->quantityToPick, $this->lines));
    }

    public function id(): PickListId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function waveId(): WaveId
    {
        return $this->waveId;
    }

    public function status(): PickListStatus
    {
        return $this->status;
    }

    /**
     * @return non-empty-list<PickLine>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
