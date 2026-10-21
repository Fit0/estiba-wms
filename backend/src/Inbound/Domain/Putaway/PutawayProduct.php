<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Putaway;

use App\Inbound\Domain\ProductId;
use App\Shared\Domain\ProductFamily;
use App\Shared\Domain\TurnoverClass;

/**
 * What a putaway strategy needs to know about the product it places.
 */
final readonly class PutawayProduct
{
    public function __construct(
        public ProductId $id,
        public ?ProductFamily $family,
        public TurnoverClass $turnoverClass,
    ) {
    }
}
