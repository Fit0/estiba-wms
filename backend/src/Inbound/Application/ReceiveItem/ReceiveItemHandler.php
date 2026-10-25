<?php

declare(strict_types=1);

namespace App\Inbound\Application\ReceiveItem;

use App\Inbound\Application\ReceiptLookup;
use App\Inbound\Application\ReceivableProducts;
use App\Inbound\Domain\LotNumber;
use App\Inbound\Domain\Receipt;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Clock;

final readonly class ReceiveItemHandler implements CommandHandler
{
    public function __construct(
        private ReceiptLookup $lookup,
        private ReceivableProducts $products,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(ReceiveItem $command): Receipt
    {
        $receipt = $this->lookup->get($command->receiptId);

        $receipt->receiveItem(
            $this->products->byGtin($command->gtin),
            $command->units,
            null === $command->lot ? null : LotNumber::fromString($command->lot),
            $command->expiryDate,
            $command->condition,
            $this->clock->now(),
        );

        $this->events->publish(...$receipt->pullEvents());

        return $receipt;
    }
}
