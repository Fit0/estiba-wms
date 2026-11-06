<?php

declare(strict_types=1);

namespace App\Erp\Application;

/**
 * What the ERP is told when an order leaves the warehouse. It is written in the words of the warehouse;
 * the gateway turns it into the words of the ERP (ADR 0047).
 */
final readonly class ShipmentConfirmation
{
    /**
     * @param string            $orderReference the number the ERP gave the order
     * @param int               $parcels        how many parcels left, at least one
     * @param list<ShippedLine> $lines          what shipped; a product that is not listed shipped nothing
     */
    public function __construct(
        public string $orderReference,
        public \DateTimeImmutable $shippedAt,
        public string $carrier,
        public ?string $trackingNumber,
        public int $parcels,
        public array $lines,
    ) {
    }
}
