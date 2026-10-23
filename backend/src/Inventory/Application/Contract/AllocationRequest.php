<?php

declare(strict_types=1);

namespace App\Inventory\Application\Contract;

use App\Shared\Domain\TenantId;

/**
 * Units of a product to promise to one demand, such as an order line, taken from the stock of one
 * warehouse.
 *
 * The reference tells the demand: asking again with the same reference tops up the reservation
 * that already exists and never reserves twice. Because of that, `units` is the whole quantity the
 * demand needs and not what is still missing, and it cannot change between requests.
 */
final readonly class AllocationRequest
{
    public function __construct(
        public TenantId $tenantId,
        public string $reference,
        public string $productId,
        public string $warehouseId,
        public int $units,
        public \DateTimeImmutable $at,
    ) {
    }
}
