<?php

declare(strict_types=1);

namespace App\Catalog\Application\AssignGtin;

final readonly class AssignGtin
{
    public function __construct(
        public string $productId,
        public string $gtin,
    ) {
    }
}
