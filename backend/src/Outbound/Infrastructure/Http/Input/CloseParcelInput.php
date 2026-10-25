<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\Input;

/**
 * The contents arrive as plain arrays: without a PHPDoc parser the serializer cannot tell what a
 * list holds, so {@see CloseParcelContents} reads them and refuses one of the wrong shape.
 */
final readonly class CloseParcelInput
{
    /**
     * @param array<array-key, mixed> $contents each one an object with `productId` and `units`
     */
    public function __construct(
        public int $weightGrams,
        public array $contents = [],
    ) {
    }
}
