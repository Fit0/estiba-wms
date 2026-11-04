<?php

declare(strict_types=1);

namespace App\Outbound\Application\Contract;

/**
 * What outbound did in a period, and what it has open now.
 *
 * `ordersOpen` is a picture of now and ignores the period. The rest counts what happened inside it:
 * orders registered, orders shipped (dispatched), and the pick lists that were completed, with the
 * stops they confirmed, the units picked and the units missing (short picks). `pickingSeconds` is the
 * time those lists spent between their start and their completion.
 */
final readonly class OutboundFigures
{
    public function __construct(
        public int $ordersOpen,
        public int $ordersRegistered,
        public int $ordersShipped,
        public int $pickListsCompleted,
        public int $linesPicked,
        public int $unitsPicked,
        public int $shortPickLines,
        public int $unitsShort,
        public int $pickingSeconds,
    ) {
    }
}
