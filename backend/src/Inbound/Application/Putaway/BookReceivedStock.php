<?php

declare(strict_types=1);

namespace App\Inbound\Application\Putaway;

use App\Inbound\Domain\Asn;
use App\Inbound\Domain\AsnRepository;
use App\Inbound\Domain\Event\ReceiptClosed;
use App\Inbound\Domain\Receipt;
use App\Inbound\Domain\ReceiptRepository;
use App\Inventory\Application\Contract\StockArrival;
use App\Inventory\Application\Contract\StockMovements;
use App\Shared\Application\Bus\EventHandler;

/**
 * Whenever a receipt closes, its goods enter stock at the dock: damaged units in quarantine, never
 * available (INB-10). The ASN reference goes on the movements, so the ledger says where the stock
 * came from.
 */
final readonly class BookReceivedStock implements EventHandler
{
    public function __construct(
        private ReceiptRepository $receipts,
        private AsnRepository $asns,
        private StockMovements $stock,
    ) {
    }

    public function __invoke(ReceiptClosed $event): void
    {
        $receipt = $this->receipts->find($event->receiptId);
        $asn = $this->asns->find($event->asnId);
        if (!$receipt instanceof Receipt || !$asn instanceof Asn) {
            throw new \LogicException('A receipt that just closed and its ASN must exist.');
        }

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
