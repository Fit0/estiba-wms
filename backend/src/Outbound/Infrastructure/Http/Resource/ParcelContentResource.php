<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\Resource;

use App\Outbound\Domain\ParcelContent;

final readonly class ParcelContentResource
{
    public function __construct(
        public string $productId,
        public int $units,
    ) {
    }

    public static function fromDomain(ParcelContent $content): self
    {
        return new self($content->productId->value, $content->units);
    }
}
