<?php

declare(strict_types=1);

namespace App\Outbound\Application\RegisterOrder;

use App\Outbound\Domain\Order;
use App\Outbound\Domain\OrderPriority;
use App\Shared\Application\Bus\Command;

/**
 * Registers a customer order to be served from a warehouse. The lines are numbered in the order given.
 *
 * @implements Command<Order>
 */
final readonly class RegisterOrder implements Command
{
    /**
     * @param list<RegisterOrderLine> $lines
     */
    public function __construct(
        public string $reference,
        public string $warehouseId,
        public string $customer,
        public string $street,
        public string $postalCode,
        public string $city,
        public string $countryCode,
        public OrderPriority $priority,
        public \DateTimeImmutable $cutOffAt,
        public array $lines,
    ) {
    }
}
