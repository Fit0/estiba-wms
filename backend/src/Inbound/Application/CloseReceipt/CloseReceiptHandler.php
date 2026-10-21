<?php

declare(strict_types=1);

namespace App\Inbound\Application\CloseReceipt;

use App\Inbound\Application\AsnLookup;
use App\Inbound\Application\ReceiptLookup;
use App\Inbound\Domain\OperatorId;
use App\Inbound\Domain\Receipt;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Clock;
use App\Shared\Application\CurrentUser;

/**
 * The receipt closes through its ASN, which adds what arrived to its lines in the same
 * transaction. `ReceiptClosed` is published, and its policies book the stock at the dock and plan
 * the putaway, also in this transaction (ADR 0011).
 */
final readonly class CloseReceiptHandler implements CommandHandler
{
    public function __construct(
        private ReceiptLookup $receipts,
        private AsnLookup $asns,
        private CurrentUser $user,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(CloseReceipt $command): Receipt
    {
        $receipt = $this->receipts->get($command->receiptId);
        $asn = $this->asns->get($receipt->asnId()->value);

        $asn->closeReceipt($receipt, OperatorId::fromString($this->user->id()), $this->clock->now());
        $this->events->publish(...$receipt->pullEvents());

        return $receipt;
    }
}
