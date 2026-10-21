<?php

declare(strict_types=1);

namespace App\Inbound\Application\Putaway;

use App\Inbound\Domain\Event\PutawayConfirmed;
use App\Inventory\Application\Contract\StockMovements;
use App\Inventory\Application\Contract\StockTransfer;
use App\Shared\Application\Bus\EventHandler;

/**
 * Whenever a putaway is confirmed, the stock moves from the dock to the location, with its lot,
 * expiry date and status (STK-08).
 */
final readonly class MoveStockOnPutaway implements EventHandler
{
    public function __construct(private StockMovements $stock)
    {
    }

    public function __invoke(PutawayConfirmed $event): void
    {
        $goods = $event->goods;

        $this->stock->transfer(new StockTransfer(
            $event->tenantId,
            $goods->productId->value,
            $event->fromLocationId->value,
            $event->toLocationId->value,
            $goods->lot?->value,
            $goods->expiryDate,
            $goods->quarantine,
            $goods->units,
            $event->reference->value,
            $event->confirmedBy->value,
            $event->occurredAt(),
        ));
    }
}
