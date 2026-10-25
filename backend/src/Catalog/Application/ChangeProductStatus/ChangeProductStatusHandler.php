<?php

declare(strict_types=1);

namespace App\Catalog\Application\ChangeProductStatus;

use App\Catalog\Application\ProductLookup;
use App\Catalog\Domain\Product;
use App\Catalog\Domain\ProductStatus;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Clock;

final readonly class ChangeProductStatusHandler implements CommandHandler
{
    public function __construct(
        private ProductLookup $lookup,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(ChangeProductStatus $command): Product
    {
        $product = $this->lookup->get($command->productId);
        $at = $this->clock->now();

        match ($command->status) {
            ProductStatus::Blocked => $product->block($at),
            ProductStatus::Active => $product->unblock($at),
            ProductStatus::Discontinued => $product->discontinue($at),
        };

        $this->events->publish(...$product->pullEvents());

        return $product;
    }
}
