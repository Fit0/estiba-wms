<?php

declare(strict_types=1);

namespace App\Catalog\Application\ClassifyProduct;

use App\Catalog\Domain\Product;
use App\Shared\Application\Bus\Command;
use App\Shared\Domain\TurnoverClass;

/**
 * Sets both the family and the turnover class. A product without a family is sent with a null one.
 *
 * @implements Command<Product>
 */
final readonly class ClassifyProduct implements Command
{
    public function __construct(
        public string $productId,
        public ?string $family,
        public TurnoverClass $turnoverClass,
    ) {
    }
}
