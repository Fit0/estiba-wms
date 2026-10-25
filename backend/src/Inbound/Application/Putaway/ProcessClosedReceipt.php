<?php

declare(strict_types=1);

namespace App\Inbound\Application\Putaway;

use App\Inbound\Domain\Asn;
use App\Inbound\Domain\AsnRepository;
use App\Inbound\Domain\Event\ReceiptClosed;
use App\Inbound\Domain\PutawayTaskRepository;
use App\Inbound\Domain\Receipt;
use App\Inbound\Domain\ReceiptRepository;
use App\Inventory\Application\Contract\StockArrival;
use App\Inventory\Application\Contract\StockMovements;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Bus\EventHandler;

/**
 * Whenever a receipt closes, its goods enter stock at the dock, damaged units in quarantine, never
 * available (INB-10), and get putaway tasks, one for each kind of goods, with a suggested location
 * (INB-14). The ASN reference goes on the movements, so the ledger says where the stock came from.
 *
 * Both happen in one transaction, so a retry never finds one done and the other not, and the
 * receipt records that they happened: a message delivered twice books nothing twice (ADR 0012).
 */
final readonly class ProcessClosedReceipt implements EventHandler
{
    public function __construct(
        private ReceiptRepository $receipts,
        private AsnRepository $asns,
        private PutawayTaskRepository $tasks,
        private StockMovements $stock,
        private PutawayPlanner $planner,
        private EventBus $events,
    ) {
    }

    public function __invoke(ReceiptClosed $event): void
    {
        $receipt = $this->receipts->find($event->receiptId);
        $asn = $this->asns->find($event->asnId);
        if (!$receipt instanceof Receipt || !$asn instanceof Asn) {
            throw new \LogicException('A receipt that closed and its ASN must exist.');
        }

        if ($receipt->isStockBooked()) {
            return;
        }

        $this->bookStock($receipt, $asn, $event);
        foreach ($this->planner->plan($receipt, $asn->reference(), $event->occurredAt()) as $task) {
            $this->tasks->save($task);
            $this->events->publish(...$task->pullEvents());
        }
        $receipt->recordStockBooked($event->occurredAt());
        $this->events->publish(...$receipt->pullEvents());
    }

    private function bookStock(Receipt $receipt, Asn $asn, ReceiptClosed $event): void
    {
        foreach ($receipt->receivedGoods() as $goods) {
            $this->stock->receive(new StockArrival(
                $receipt->tenantId(),
                $goods->productId->value,
                $receipt->dockLocationId()->value,
                $goods->lot?->value,
                $goods->expiryDate,
                $goods->quarantine,
                $goods->units,
                $asn->reference()->value,
                $event->closedBy->value,
                $event->occurredAt(),
            ));
        }
    }
}
