<?php

declare(strict_types=1);

namespace App\Inbound\Application\ReceiveItem;

use App\Inbound\Domain\ItemCondition;
use App\Inbound\Domain\Receipt;
use App\Shared\Application\Bus\Command;

/**
 * An operator scanned a barcode at the dock and counted this many base units.
 *
 * @implements Command<Receipt>
 */
final readonly class ReceiveItem implements Command
{
    public function __construct(
        public string $receiptId,
        public string $gtin,
        public int $units,
        public ?string $lot = null,
        public ?\DateTimeImmutable $expiryDate = null,
        public ItemCondition $condition = ItemCondition::Good,
    ) {
    }
}
