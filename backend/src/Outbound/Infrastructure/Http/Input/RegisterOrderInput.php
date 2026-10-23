<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\Input;

use App\Outbound\Domain\OrderPriority;

/**
 * The lines arrive as plain arrays: without a PHPDoc parser the serializer cannot tell what a list
 * holds, so {@see RegisterOrderLines} reads them and refuses a line of the wrong shape.
 */
final readonly class RegisterOrderInput
{
    /**
     * @param array<array-key, mixed> $lines each one an object with `productId` and `units`
     */
    public function __construct(
        public string $reference,
        public string $warehouseId,
        public string $customer,
        public ShipToInput $shipTo,
        public \DateTimeImmutable $cutOffAt,
        public OrderPriority $priority = OrderPriority::Normal,
        public array $lines = [],
    ) {
    }
}
