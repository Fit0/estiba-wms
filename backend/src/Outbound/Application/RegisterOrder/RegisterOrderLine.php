<?php

declare(strict_types=1);

namespace App\Outbound\Application\RegisterOrder;

final readonly class RegisterOrderLine
{
    public function __construct(
        public string $productId,
        public int $units,
    ) {
    }
}
