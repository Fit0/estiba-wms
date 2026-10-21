<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Http\Input;

/**
 * The lines arrive as plain arrays: without a PHPDoc parser the serializer cannot tell what a list
 * holds, so {@see RegisterAsnLines} reads them and refuses a line of the wrong shape.
 */
final readonly class RegisterAsnInput
{
    /**
     * @param array<array-key, mixed> $lines each one an object with `productId`, `units` and, optionally, `lot` and `expiryDate`
     */
    public function __construct(
        public string $reference,
        public string $supplier,
        public \DateTimeImmutable $expectedDate,
        public array $lines = [],
    ) {
    }
}
