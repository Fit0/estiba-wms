<?php

declare(strict_types=1);

namespace App\Catalog\Application\ClassifyProduct;

use App\Catalog\Application\ProductLookup;
use App\Catalog\Domain\Product;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Clock;
use App\Shared\Domain\ProductFamily;

final readonly class ClassifyProductHandler implements CommandHandler
{
    public function __construct(
        private ProductLookup $lookup,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(ClassifyProduct $command): Product
    {
        $product = $this->lookup->get($command->productId);

        $product->classify(
            null === $command->family ? null : ProductFamily::fromString($command->family),
            $command->turnoverClass,
            $this->clock->now(),
        );

        $this->events->publish(...$product->pullEvents());

        return $product;
    }
}
