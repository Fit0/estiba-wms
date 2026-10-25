<?php

declare(strict_types=1);

namespace App\Inbound\Domain;

use App\Inbound\Domain\Event\PutawayConfirmed;
use App\Inbound\Domain\Event\PutawayTaskPlanned;
use App\Inbound\Domain\Event\PutawayTaskSplit;
use App\Inbound\Domain\Exception\InvalidPutawayDestination;
use App\Inbound\Domain\Exception\InvalidPutawaySplit;
use App\Inbound\Domain\Exception\OverrideReasonRequired;
use App\Inbound\Domain\Exception\PutawayTaskAlreadyConfirmed;
use App\Inbound\Domain\Putaway\PutawayDestination;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\TenantId;

/**
 * Received goods waiting at the dock to go to a storage location, and where they went.
 *
 * The tasks of a receipt hold, together, exactly what it received (INB-14): they are planned from
 * its goods, and a task only ever gives units to a new task ({@see split()}), never loses them.
 */
final class PutawayTask extends AggregateRoot
{
    private PutawayTaskStatus $status = PutawayTaskStatus::Pending;

    private ?LocationId $confirmedLocationId = null;

    private ?OverrideReason $overrideReason = null;

    private ?OperatorId $confirmedBy = null;

    private ?\DateTimeImmutable $confirmedAt = null;

    private ?\DateTimeImmutable $stockMovedAt = null;

    private function __construct(
        private readonly PutawayTaskId $id,
        private readonly TenantId $tenantId,
        private readonly ReceiptId $receiptId,
        private readonly AsnReference $reference,
        private ReceivedGoods $goods,
        private readonly LocationId $sourceLocationId,
        private readonly ?LocationId $suggestedLocationId,
        private readonly \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param LocationId|null $suggestedLocationId null when no strategy found a free location: the operator chooses
     */
    public static function plan(
        PutawayTaskId $id,
        TenantId $tenantId,
        ReceiptId $receiptId,
        AsnReference $reference,
        ReceivedGoods $goods,
        LocationId $sourceLocationId,
        ?LocationId $suggestedLocationId,
        \DateTimeImmutable $at,
    ): self {
        $task = new self($id, $tenantId, $receiptId, $reference, $goods, $sourceLocationId, $suggestedLocationId, $at);
        $task->record(new PutawayTaskPlanned($id, $tenantId, $receiptId, $suggestedLocationId, $at));

        return $task;
    }

    /**
     * The goods are in the destination. All the units of the task move (INB-12): to move fewer,
     * split the task first.
     *
     * @throws PutawayTaskAlreadyConfirmed
     * @throws InvalidPutawayDestination   INB-11
     * @throws OverrideReasonRequired      INB-13
     */
    public function confirm(PutawayDestination $destination, ?OverrideReason $reason, OperatorId $by, \DateTimeImmutable $at): void
    {
        $this->assertPending();
        $this->assertSuitable($destination);

        $suggested = $this->suggestedLocationId;
        $override = $suggested instanceof LocationId && !$suggested->equals($destination->locationId);
        if ($override && !$reason instanceof OverrideReason) {
            throw OverrideReasonRequired::insteadOf($suggested);
        }

        $this->status = PutawayTaskStatus::Confirmed;
        $this->confirmedLocationId = $destination->locationId;
        $this->overrideReason = $override ? $reason : null;
        $this->confirmedBy = $by;
        $this->confirmedAt = $at;
        $this->record(new PutawayConfirmed($this->id, $this->tenantId, $this->goods, $this->sourceLocationId, $destination->locationId, $this->reference, $by, $at));
    }

    /**
     * Gives some of the units to a new task with the same goods and suggestion, so they can go to
     * another location. Both tasks keep at least one unit, and together they hold what this one
     * held (INB-12, INB-14).
     *
     * @throws PutawayTaskAlreadyConfirmed
     * @throws InvalidPutawaySplit
     */
    public function split(PutawayTaskId $newTaskId, int $units, \DateTimeImmutable $at): self
    {
        $this->assertPending();
        if ($units < 1 || $units >= $this->goods->units) {
            throw InvalidPutawaySplit::of($units, $this->goods->units);
        }

        $this->goods = $this->goods->withUnits($this->goods->units - $units);
        $this->record(new PutawayTaskSplit($this->id, $newTaskId, $units, $at));

        return self::plan($newTaskId, $this->tenantId, $this->receiptId, $this->reference, $this->goods->withUnits($units), $this->sourceLocationId, $this->suggestedLocationId, $at);
    }

    public function id(): PutawayTaskId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function receiptId(): ReceiptId
    {
        return $this->receiptId;
    }

    public function reference(): AsnReference
    {
        return $this->reference;
    }

    public function goods(): ReceivedGoods
    {
        return $this->goods;
    }

    public function sourceLocationId(): LocationId
    {
        return $this->sourceLocationId;
    }

    public function suggestedLocationId(): ?LocationId
    {
        return $this->suggestedLocationId;
    }

    public function status(): PutawayTaskStatus
    {
        return $this->status;
    }

    public function confirmedLocationId(): ?LocationId
    {
        return $this->confirmedLocationId;
    }

    public function overrideReason(): ?OverrideReason
    {
        return $this->overrideReason;
    }

    public function confirmedBy(): ?OperatorId
    {
        return $this->confirmedBy;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function confirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    /**
     * The policy that moves the stock of a confirmed task says so here, in the same transaction. It
     * runs again when its message is redelivered, so it asks {@see isStockMoved()} first and a
     * second run moves nothing (ADR 0012).
     */
    public function recordStockMoved(\DateTimeImmutable $at): void
    {
        $this->stockMovedAt ??= $at;
    }

    public function isStockMoved(): bool
    {
        return $this->stockMovedAt instanceof \DateTimeImmutable;
    }

    public function stockMovedAt(): ?\DateTimeImmutable
    {
        return $this->stockMovedAt;
    }

    private function assertPending(): void
    {
        if (PutawayTaskStatus::Pending !== $this->status) {
            throw PutawayTaskAlreadyConfirmed::forId($this->id);
        }
    }

    /**
     * INB-11: an active location that stores goods like these, in a zone of their storage class,
     * with room for the units. And not the dock they are on.
     */
    private function assertSuitable(PutawayDestination $destination): void
    {
        $location = $destination->locationId;

        match (true) {
            $location->equals($this->sourceLocationId) => throw InvalidPutawayDestination::sameAsSource($location),
            !$destination->active => throw InvalidPutawayDestination::blocked($location),
            !$destination->storesTheGoods => throw InvalidPutawayDestination::wrongType($location),
            !$destination->suitsTheStorageClass => throw InvalidPutawayDestination::storageClass($location),
            !$destination->hasRoom => throw InvalidPutawayDestination::noRoom($location),
            default => null,
        };
    }
}
