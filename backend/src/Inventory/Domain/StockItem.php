<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Inventory\Domain\Event\StockMovementRecorded;
use App\Inventory\Domain\Exception\MovementQuantityNotPositive;
use App\Inventory\Domain\Exception\NegativeStockNotAllowed;
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

    /** @var list<StockMovement> */
    private array $recorded = [];

    private function __construct(
        private readonly StockItemId $id,
        private readonly TenantId $tenantId,
        private readonly StockKey $key,
    ) {
        $this->onHand = Quantity::zero();
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
        $this->onHand = $after;
        $this->recorded[] = new StockMovement($movementId, $this->tenantId, $this->id, $type, $change, $after, $reference, $reason, $author, $at);
        $this->record(new StockMovementRecorded($movementId, $this->id, $type, $change, $at));
    }
}
