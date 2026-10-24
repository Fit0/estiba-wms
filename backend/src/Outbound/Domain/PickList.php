<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

use App\Outbound\Domain\Event\PickLineConfirmed;
use App\Outbound\Domain\Event\PickListCompleted;
use App\Outbound\Domain\Event\PickListGenerated;
use App\Outbound\Domain\Event\PickListStarted;
use App\Outbound\Domain\Event\ShortPickReported;
use App\Outbound\Domain\Exception\InvalidPickList;
use App\Outbound\Domain\Exception\PickedQuantityInvalid;
use App\Outbound\Domain\Exception\PickExceedsRequested;
use App\Outbound\Domain\Exception\PickLineAlreadyConfirmed;
use App\Outbound\Domain\Exception\PickLineNotFound;
use App\Outbound\Domain\Exception\PickListAlreadyAssigned;
use App\Outbound\Domain\Exception\PickListAlreadyPicked;
use App\Outbound\Domain\Exception\PickListAlreadyStarted;
use App\Outbound\Domain\Exception\PickListNotInProgress;
use App\Outbound\Domain\Exception\PickScanMismatch;
use App\Outbound\Domain\Exception\ShortPickReasonRequired;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\TenantId;

/**
 * The stops one operator walks, already in the order of the route. The list is made when a wave is
 * released, from the stock that was reserved for its orders, and it asks for exactly those units.
 *
 * An operator is assigned (OUT-12), starts the list, and confirms each stop by scanning where they are,
 * what they took and its lot (OUT-11), picking what was asked or less with a reason (OUT-10). The list is
 * complete when every stop is confirmed (OUT-13).
 */
final class PickList extends AggregateRoot
{
    private PickListStatus $status = PickListStatus::Generated;

    private ?OperatorId $operatorId = null;

    private ?\DateTimeImmutable $startedAt = null;

    private ?\DateTimeImmutable $completedAt = null;

    /**
     * @param non-empty-list<PickLine> $lines
     */
    private function __construct(
        private readonly PickListId $id,
        private readonly TenantId $tenantId,
        private readonly WaveId $waveId,
        private array $lines,
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
     * A supervisor says who walks the list. It can be changed until the operator starts it (OUT-12).
     *
     * @throws PickListAlreadyStarted OUT-12
     */
    public function assign(OperatorId $operator): void
    {
        if (PickListStatus::Generated !== $this->status) {
            throw PickListAlreadyStarted::forId($this->id->value);
        }

        $this->operatorId = $operator;
    }

    /**
     * The operator starts the list. One that was not assigned to anyone becomes theirs (OUT-12).
     *
     * @throws PickListAlreadyAssigned OUT-12
     * @throws PickListNotInProgress   when it is completed or cancelled
     */
    public function start(OperatorId $operator, \DateTimeImmutable $at): void
    {
        $this->assertOperator($operator);

        if (PickListStatus::InProgress === $this->status) {
            return;
        }
        if (PickListStatus::Generated !== $this->status) {
            throw PickListNotInProgress::forStatus($this->status);
        }

        $this->operatorId = $operator;
        $this->status = PickListStatus::InProgress;
        $this->startedAt = $at;
        $this->record(new PickListStarted($this->id, $operator, $this->tenantId, $at));
    }

    /**
     * Confirms a stop: the operator scanned where they are, what they took and its lot, and says how
     * many units they picked. Fewer than asked is a short pick and needs a reason.
     *
     * Everything is checked before anything changes. The list is complete when this was the last stop.
     *
     * @throws PickListNotInProgress    when the list is not being walked
     * @throws PickListAlreadyAssigned  OUT-12
     * @throws PickLineNotFound
     * @throws PickLineAlreadyConfirmed
     * @throws PickScanMismatch         OUT-11
     * @throws PickedQuantityInvalid    OUT-10
     * @throws PickExceedsRequested     OUT-10
     * @throws ShortPickReasonRequired  OUT-10
     */
    public function confirmLine(int $position, ScannedPick $scan, int $units, ?ShortPickReason $reason, OperatorId $operator, \DateTimeImmutable $at): PickLine
    {
        if (PickListStatus::InProgress !== $this->status) {
            throw PickListNotInProgress::forStatus($this->status);
        }
        $this->assertOperator($operator);

        $index = $this->indexOf($position);
        $line = $this->lines[$index];
        if ($line->confirmed) {
            throw PickLineAlreadyConfirmed::at($position);
        }

        $this->assertScanMatches($line, $scan);

        if ($units < 0) {
            throw PickedQuantityInvalid::forUnits($units);
        }
        if ($units > $line->quantityToPick) {
            throw PickExceedsRequested::forLine($position, $units, $line->quantityToPick);
        }
        if ($units < $line->quantityToPick && !$reason instanceof ShortPickReason) {
            throw ShortPickReasonRequired::forLine($position, $units, $line->quantityToPick);
        }

        $confirmed = $line->confirmedWith($units, $reason);
        $this->lines = array_map(static fn (PickLine $existing): PickLine => $existing->position === $position ? $confirmed : $existing, $this->lines);
        $this->record(new PickLineConfirmed($this->id, $position, $units, $this->tenantId, $at));
        if ($confirmed->missingUnits() > 0) {
            $this->record(new ShortPickReported($this->id, $position, $confirmed->orderId, $confirmed->orderLineNumber, $confirmed->missingUnits(), $this->tenantId, $at));
        }

        if ($this->everyLineIsConfirmed()) {
            $this->status = PickListStatus::Completed;
            $this->completedAt = $at;
            $this->record(new PickListCompleted($this->id, $this->waveId, $this->tenantId, $at));
        }

        return $confirmed;
    }

    /**
     * Calls the list off, because its wave was. Doing it again changes nothing. A list with a pick
     * confirmed cannot be called off: the goods have left the shelves.
     *
     * @throws PickListAlreadyPicked
     */
    public function cancel(): void
    {
        foreach ($this->lines as $line) {
            if ($line->confirmed) {
                throw PickListAlreadyPicked::forId($this->id->value);
            }
        }

        $this->status = PickListStatus::Cancelled;
    }

    /**
     * The units the list asks for, in all its stops.
     */
    public function unitsToPick(): int
    {
        return array_sum(array_map(static fn (PickLine $line): int => $line->quantityToPick, $this->lines));
    }

    /**
     * The units picked so far.
     */
    public function unitsPicked(): int
    {
        return array_sum(array_map(static fn (PickLine $line): int => $line->quantityPicked, $this->lines));
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

    public function operatorId(): ?OperatorId
    {
        return $this->operatorId;
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

    public function startedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function completedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    private function assertOperator(OperatorId $operator): void
    {
        if ($this->operatorId instanceof OperatorId && !$this->operatorId->equals($operator)) {
            throw PickListAlreadyAssigned::to($this->operatorId);
        }
    }

    private function indexOf(int $position): int
    {
        foreach ($this->lines as $index => $line) {
            if ($line->position === $position) {
                return $index;
            }
        }

        throw PickLineNotFound::at($position);
    }

    /**
     * The location is compared by its label, the product by what its barcode belongs to, and the lot
     * only when the line has one (OUT-11).
     */
    private function assertScanMatches(PickLine $line, ScannedPick $scan): void
    {
        if (strtoupper(trim($scan->locationCode)) !== strtoupper($line->locationCode)) {
            throw PickScanMismatch::of(PickScanMismatch::LOCATION, $line->position);
        }
        if (!$scan->productId instanceof ProductId || !$scan->productId->equals($line->productId)) {
            throw PickScanMismatch::of(PickScanMismatch::PRODUCT, $line->position);
        }
        if (null !== $line->lot && trim((string) $scan->lot) !== $line->lot) {
            throw PickScanMismatch::of(PickScanMismatch::LOT, $line->position);
        }
    }

    private function everyLineIsConfirmed(): bool
    {
        return array_all($this->lines, static fn ($line) => $line->confirmed);
    }
}
