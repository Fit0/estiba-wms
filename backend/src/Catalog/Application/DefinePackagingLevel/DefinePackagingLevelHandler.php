<?php

declare(strict_types=1);

namespace App\Catalog\Application\DefinePackagingLevel;

use App\Catalog\Application\ProductLookup;
use App\Catalog\Domain\PackagingLevel;
use App\Catalog\Domain\Product;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Clock;

final readonly class DefinePackagingLevelHandler implements CommandHandler
{
    public function __construct(
        private ProductLookup $lookup,
        private Clock $clock,
    ) {
    }

    public function __invoke(DefinePackagingLevel $command): Product
    {
        $product = $this->lookup->get($command->productId);

        $product->definePackagingLevel(PackagingLevel::of($command->type, $command->baseUnits), $this->clock->now());

        return $product;
    }
}
