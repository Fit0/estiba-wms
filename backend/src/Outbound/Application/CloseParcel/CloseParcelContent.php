<?php

declare(strict_types=1);

namespace App\Outbound\Application\CloseParcel;

final readonly class CloseParcelContent
{
    public function __construct(
        public string $productId,
        public int $units,
    ) {
    }
}
