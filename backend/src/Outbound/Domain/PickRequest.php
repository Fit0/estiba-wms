<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

/**
 * Units of a product to pick from a place, for one order line, before anyone knows in which order
 * to walk. {@see SerpentineRoute} puts the requests in that order.
 *
 * `locationCode` is `aisle-module-level`, such as `A-03-02`. The zone and pick sequences are the
 * location's own: they say where in the warehouse it is.
 */
final readonly class PickRequest
{
    public function __construct(
        public OrderId $orderId,
        public int $orderLineNumber,
        public ProductId $productId,
        public string $stockItemId,
        public string $locationId,
        public string $locationCode,
        public int $zonePickSequence,
        public int $pickSequence,
        public ?string $lot,
        public ?\DateTimeImmutable $expiryDate,
        public int $units,
    ) {
    }

    /**
     * The letters before the first dash of the location code.
     */
    public function aisle(): string
    {
        return explode('-', $this->locationCode, 2)[0];
    }
}
