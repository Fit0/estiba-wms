<?php

declare(strict_types=1);

namespace App\Catalog\Application\AssignGtin;

use App\Catalog\Application\ProductLookup;
use App\Catalog\Domain\Gtin;
use App\Catalog\Domain\Product;
use App\Catalog\Domain\ProductRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Clock;

final readonly class AssignGtinHandler implements CommandHandler
{
    public function __construct(
        private ProductLookup $lookup,
        private ProductRepository $products,
        private EventBus $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(AssignGtin $command): Product
    {
        $product = $this->lookup->get($command->productId);

        $product->assignGtin(Gtin::fromString($command->gtin), $this->products, $this->clock->now());
        $this->products->save($product);

        $this->events->publish(...$product->pullEvents());

        return $product;
    }
}
