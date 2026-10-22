<?php

declare(strict_types=1);

namespace App\Inventory\Application;

use App\Inventory\Application\Contract\StockArrival;
use App\Inventory\Application\Contract\StockMovements;
use App\Inventory\Application\Contract\StockTransfer;
use App\Inventory\Domain\AuthorId;
use App\Inventory\Domain\Exception\NegativeStockNotAllowed;
use App\Inventory\Domain\LocationId;
use App\Inventory\Domain\LotNumber;
use App\Inventory\Domain\MovementReference;
use App\Inventory\Domain\ProductId;
use App\Inventory\Domain\Quantity;
use App\Inventory\Domain\StockItem;
use App\Inventory\Domain\StockItemId;
use App\Inventory\Domain\StockItemRepository;
use App\Inventory\Domain\StockKey;
use App\Inventory\Domain\StockMovementId;
use App\Inventory\Domain\StockStatus;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\IdGenerator;
use App\Shared\Domain\TenantId;

/**
 * Answers the {@see StockMovements} contract on the ledger. A stock item that does not exist yet is
 * opened when units arrive in it.
 *
 * Every change publishes its movements, so the stock read model follows the ledger (ADR 0013). They
 * leave once the caller's transaction commits.
 */
final readonly class LedgerStockMovements implements StockMovements
{
    public function __construct(
        private StockItemRepository $stockItems,
        private IdGenerator $ids,
        private EventBus $events,
    ) {
    }

    #[\Override]
    public function receive(StockArrival $arrival): void
    {
        $key = $this->key($arrival->productId, $arrival->locationId, $arrival->lot, $arrival->expiryDate, $arrival->quarantine);
        $stockItem = $this->stockItemFor($arrival->tenantId, $key);

        $stockItem->receive(
            StockMovementId::fromString($this->ids->next()),
            Quantity::of($arrival->units),
            MovementReference::fromString($arrival->reference),
            AuthorId::fromString($arrival->authorId),
            $arrival->at,
        );

        $this->save($stockItem);
    }

    #[\Override]
    public function transfer(StockTransfer $transfer): void
    {
        $from = $this->key($transfer->productId, $transfer->fromLocationId, $transfer->lot, $transfer->expiryDate, $transfer->quarantine);
        $to = $this->key($transfer->productId, $transfer->toLocationId, $transfer->lot, $transfer->expiryDate, $transfer->quarantine);

        $source = $this->stockItems->findByKey($transfer->tenantId, $from)
            ?? throw NegativeStockNotAllowed::taking($transfer->units, 0); // STK-01: nothing to take from
        $destination = $this->stockItemFor($transfer->tenantId, $to);

        $source->transferTo(
            $destination,
            Quantity::of($transfer->units),
            StockMovementId::fromString($this->ids->next()),
            StockMovementId::fromString($this->ids->next()),
            MovementReference::fromString($transfer->reference),
            AuthorId::fromString($transfer->authorId),
            $transfer->at,
        );

        $this->save($source);
        $this->save($destination);
    }

    private function save(StockItem $stockItem): void
    {
        $this->stockItems->save($stockItem);
        $this->events->publish(...$stockItem->pullEvents());
    }

    private function stockItemFor(TenantId $tenantId, StockKey $key): StockItem
    {
        return $this->stockItems->findByKey($tenantId, $key)
            ?? StockItem::open(StockItemId::fromString($this->ids->next()), $tenantId, $key);
    }

    private function key(string $productId, string $locationId, ?string $lot, ?\DateTimeImmutable $expiryDate, bool $quarantine): StockKey
    {
        return new StockKey(
            ProductId::fromString($productId),
            LocationId::fromString($locationId),
            null === $lot ? null : LotNumber::fromString($lot),
            $expiryDate,
            $quarantine ? StockStatus::Quarantine : StockStatus::Available,
        );
    }
}
