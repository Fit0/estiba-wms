<?php

declare(strict_types=1);

namespace App\Inbound\Application\ApproveOverReceipt;

use App\Inbound\Application\ReceiptLookup;
use App\Inbound\Domain\Receipt;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Clock;

final readonly class ApproveOverReceiptHandler implements CommandHandler
{
    public function __construct(
        private ReceiptLookup $lookup,
        private Clock $clock,
    ) {
    }

    public function __invoke(ApproveOverReceipt $command): Receipt
    {
        $receipt = $this->lookup->get($command->receiptId);

        $receipt->approveOverReceipt($this->clock->now());

        return $receipt;
    }
}
