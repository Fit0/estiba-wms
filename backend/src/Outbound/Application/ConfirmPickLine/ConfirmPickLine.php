<?php

declare(strict_types=1);

namespace App\Outbound\Application\ConfirmPickLine;

use App\Outbound\Domain\PickList;
use App\Shared\Application\Bus\Command;

/**
 * The signed-in operator confirms a stop of their pick list: where they are, the barcode of the product,
 * its lot, and how many units they picked. Fewer than asked needs a reason (OUT-10, OUT-11).
 *
 * @implements Command<PickList>
 */
final readonly class ConfirmPickLine implements Command
{
    public function __construct(
        public string $pickListId,
        public int $position,
        public string $location,
        public string $gtin,
        public ?string $lot,
        public int $units,
        public ?string $reason = null,
    ) {
    }
}
