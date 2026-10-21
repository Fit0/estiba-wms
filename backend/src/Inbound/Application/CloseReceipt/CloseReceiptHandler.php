<?php

declare(strict_types=1);

namespace App\Inbound\Application\CloseReceipt;

use App\Inbound\Application\AsnLookup;
use App\Inbound\Application\ReceiptLookup;
use App\Inbound\Domain\Receipt;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Clock;

/**
 * The receipt closes through its ASN, which adds what arrived to its lines in the same
 * transaction. Booking the stock follows from `ReceiptClosed` (E03-04).
 */
final readonly class CloseReceiptHandler implements CommandHandler
{
    public function __construct(
        private ReceiptLookup $receipts,
        private AsnLookup $asns,
        private Clock $clock,
    ) {
    }

    public function __invoke(CloseReceipt $command): Receipt
    {
        $receipt = $this->receipts->get($command->receiptId);
        $asn = $this->asns->get($receipt->asnId()->value);

        $asn->closeReceipt($receipt, $this->clock->now());

        return $receipt;
    }
}
