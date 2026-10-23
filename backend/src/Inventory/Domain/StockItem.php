<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Inventory\Domain\Event\StockMovementRecorded;
use App\Inventory\Domain\Exception\MovementQuantityNotPositive;
use App\Inventory\Domain\Exception\NegativeStockNotAllowed;
use App\Inventory\Domain\Exception\NotEnoughAvailableStock;
use App\Inventory\Domain\Exception\ReleaseExceedsReserved;
use App\Inventory\Domain\Exception\ReservationQuantityNotPositive;
use App\Inventory\Domain\Exception\StockNotReservable;
use App\Inventory\Domain\Exception\TransferAcrossTenants;
use App\Inventory\Domain\Exception\TransferBetweenDifferentProducts;
use App\Inventory\Domain\Exception\TransferToSameStockItem;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\TenantId;

/**
 * The units of one product in one place, of one lot and expiry date, with one status.
 *
 * Stock is a ledger. The units change only through the methods below, and each of them records
 * exactly one {@see StockMovement} for the change it makes (STK-03). The movements are not held by
 * the stock item: it keeps the ones it recorded since it was loaded, for the repository to save
 * with it ({@see pullMovements()}). The rest of the ledger is read from the store.
 *
 * Every method checks everything before it changes anything, so a refused change leaves no trace.
 */
final class StockItem extends AggregateRoot
{
    private Quantity $onHand;

    /** Units promised to orders that are still in the stock item (STK-02). Reserving moves no units, so it records no movement. */
    private Quantity $reserved;

    /** When units first arrived. It orders stock for FIFO allocation (STK-12). */
    private ?\DateTimeImmutable $firstReceivedAt = null;

    /**
     * Counts the saves of the stock item. The persistence layer owns it: when two requests change the
     * same stock item at once, the second one to save is refused instead of overwriting the first.
     * That is what keeps two orders from reserving the same units (STK-02).
     */
    private int $version = 1;

    /** @var list<StockMovement> */
    private array $recorded = [];

    private function __construct(
        private readonly StockItemId $id,
        private readonly TenantId $tenantId,
        private readonly StockKey $key,
    ) {
        $this->onHand = Quantity::zero();
        $this->reserved = Quantity::zero();
    }

    /**
     * A stock item with no units yet. Opening it records no movement, because nothing changed.
     */
    public static function open(StockItemId $id, TenantId $tenantId, StockKey $key): self
    {
        return new self($id, $tenantId, $key);
    }

    /**
     * Units arrive: from a receipt, or from a count that found more than expected.
     *
     * @throws MovementQuantityNotPositive STK-03
     */
    public function receive(StockMovementId $movementId, Quantity $quantity, ?MovementReference $reference, AuthorId $author, \DateTimeImmutable $at): void
    {
        $this->assertChanges(MovementType::Receipt, $quantity);

        $this->applyChange(MovementType::Receipt, $movementId, $this->onHand->plus($quantity), $quantity->units, $reference, null, $author, $at);
    }

    /**
     * Units leave: shipped, consumed or scrapped.
     *
     * @throws MovementQuantityNotPositive STK-03
     * @throws NegativeStockNotAllowed     STK-01
     */
    public function issue(StockMovementId $movementId, Quantity $quantity, ?MovementReference $reference, AuthorId $author, \DateTimeImmutable $at): void
    {
        $this->assertChanges(MovementType::Issue, $quantity);

        $this->applyChange(MovementType::Issue, $movementId, $this->onHand->minus($quantity), -$quantity->units, $reference, null, $author, $at);
    }

    /**
     * The count says the system was wrong. A positive change adds units and a negative one takes them.
     * It always has a reason and an author (STK-09).
     *
     * @throws MovementQuantityNotPositive STK-03
     * @throws NegativeStockNotAllowed     STK-01
     */
    public function adjust(StockMovementId $movementId, int $change, AdjustmentReason $reason, AuthorId $author, \DateTimeImmutable $at): void
    {
        if (0 === $change) {
            throw MovementQuantityNotPositive::for(MovementType::Adjustment);
        }

        $after = $change > 0
            ? $this->onHand->plus(Quantity::of($change))
            : $this->onHand->minus(Quantity::of(-$change));

        $this->applyChange(MovementType::Adjustment, $movementId, $after, $change, null, $reason, $author, $at);
    }

    /**
     * Moves units to another stock item of the same product: another location, or another status.
     * Both stock items change or neither does (STK-08), and each records its own movement.
     *
     * @throws TransferToSameStockItem          STK-08
     * @throws TransferAcrossTenants            STK-08
     * @throws TransferBetweenDifferentProducts STK-08
     * @throws MovementQuantityNotPositive      STK-03
     * @throws NegativeStockNotAllowed          STK-01
     */
    public function transferTo(self $destination, Quantity $quantity, StockMovementId $outMovementId, StockMovementId $inMovementId, ?MovementReference $reference, AuthorId $author, \DateTimeImmutable $at): void
    {
        if ($this->id->equals($destination->id)) {
            throw TransferToSameStockItem::of($this->id);
        }
        if (!$this->tenantId->equals($destination->tenantId)) {
            throw TransferAcrossTenants::attempted();
        }
        if (!$this->key->productId->equals($destination->key->productId)) {
            throw TransferBetweenDifferentProducts::between($this->key->productId, $destination->key->productId);
        }
        $this->assertChanges(MovementType::TransferOut, $quantity);

        $remaining = $this->onHand->minus($quantity);
        $arriving = $destination->onHand->plus($quantity);

        $this->applyChange(MovementType::TransferOut, $outMovementId, $remaining, -$quantity->units, $reference, null, $author, $at);
        $destination->applyChange(MovementType::TransferIn, $inMovementId, $arriving, $quantity->units, $reference, null, $author, $at);
    }

    /**
     * Promises units to an order. Only available stock can be reserved (STK-06), and never more
     * than is free (STK-02). The units stay in the stock item, so no movement is recorded.
     *
     * @throws StockNotReservable             STK-06
     * @throws ReservationQuantityNotPositive STK-11
     * @throws NotEnoughAvailableStock        STK-02
     */
    public function reserve(Quantity $quantity): void
    {
        if (StockStatus::Available !== $this->key->status) {
            throw StockNotReservable::inStatus($this->id, $this->key->status);
        }
        if ($quantity->isZero()) {
            throw ReservationQuantityNotPositive::ofZero();
        }

        $available = $this->available();
        if ($quantity->units > $available->units) {
            throw NotEnoughAvailableStock::reserving($quantity->units, $available->units);
        }

        $this->reserved = $this->reserved->plus($quantity);
    }

    /**
     * Takes back a promise: the units are free again.
     *
     * @throws ReleaseExceedsReserved STK-02
     */
    public function unreserve(Quantity $quantity): void
    {
        if ($quantity->units > $this->reserved->units) {
            throw ReleaseExceedsReserved::releasing($quantity->units, $this->reserved->units);
        }

        $this->reserved = $this->reserved->minus($quantity);
    }

    /**
     * The units nobody has promised yet.
     */
    public function available(): Quantity
    {
        return $this->onHand->minus($this->reserved);
    }

    public function reserved(): Quantity
    {
        return $this->reserved;
    }

    /**
     * How many times the stock item has been saved, for the persistence layer's lock.
     */
    public function version(): int
    {
        return $this->version;
    }

    public function firstReceivedAt(): ?\DateTimeImmutable
    {
        return $this->firstReceivedAt;
    }

    /**
     * The movements recorded since the stock item was created or last pulled, oldest first.
     * The repository takes them when it saves, so each is stored once.
     *
     * @return list<StockMovement>
     */
    public function pullMovements(): array
    {
        $movements = $this->recorded;
        $this->recorded = [];

        return $movements;
    }

    public function id(): StockItemId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function key(): StockKey
    {
        return $this->key;
    }

    public function onHand(): Quantity
    {
        return $this->onHand;
    }

    private function assertChanges(MovementType $type, Quantity $quantity): void
    {
        if ($quantity->isZero()) {
            throw MovementQuantityNotPositive::for($type);
        }
    }

    /**
     * The one place where the units change, so no change goes without its movement (STK-03).
     * The new balance was worked out by the caller, which has already checked it (STK-01).
     */
    private function applyChange(MovementType $type, StockMovementId $movementId, Quantity $after, int $change, ?MovementReference $reference, ?AdjustmentReason $reason, AuthorId $author, \DateTimeImmutable $at): void
    {
        // STK-02, STK-10: units promised to an order cannot leave.
        if ($after->units < $this->reserved->units) {
            throw NotEnoughAvailableStock::taking(-$change, $this->available()->units);
        }

        $this->onHand = $after;
        if ($change > 0 && !$this->firstReceivedAt instanceof \DateTimeImmutable) {
            $this->firstReceivedAt = $at;
        }
        $this->recorded[] = new StockMovement($movementId, $this->tenantId, $this->id, $type, $change, $after, $reference, $reason, $author, $at);
        $this->record(new StockMovementRecorded($movementId, $this->tenantId, $this->id, $type, $change, $at));
    }
}
