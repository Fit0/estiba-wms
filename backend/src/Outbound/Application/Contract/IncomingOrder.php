<?php

declare(strict_types=1);

namespace App\Outbound\Application\Contract;

/**
 * A customer order that another system took and Outbound has to serve. It is already in the words of
 * the warehouse: the system it came from has translated it (ADR 0047). Lines keep the order they have.
 */
final readonly class IncomingOrder
{
    /**
     * @param list<IncomingOrderLine> $lines
     */
    public function __construct(
        public string $reference,
        public string $warehouseId,
        public string $customer,
        public string $street,
        public string $postalCode,
        public string $city,
        public string $countryCode,
        public IncomingOrderPriority $priority,
        public \DateTimeImmutable $cutOffAt,
        public array $lines,
    ) {
    }
}
