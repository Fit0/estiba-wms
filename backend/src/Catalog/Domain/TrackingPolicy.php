<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

use App\Catalog\Domain\Exception\ExpiryRequiresLotTracking;
use App\Catalog\Domain\Exception\InvalidShelfLife;

/**
 * What the warehouse must record about each unit it receives: lot, expiry date, and how
 * much shelf life must remain on receipt and on shipment.
 *
 * Stock tracked by expiry date is allocated first-expired-first-out (FEFO).
 */
final readonly class TrackingPolicy
{
    private function __construct(
        public bool $trackedByLot,
        public bool $trackedByExpiry,
        public ?int $minShelfLifeAtReceiptDays,
        public ?int $minShelfLifeAtShipmentDays,
    ) {
    }

    /**
     * @throws ExpiryRequiresLotTracking CAT-04
     * @throws InvalidShelfLife          CAT-07
     */
    public static function of(
        bool $trackedByLot,
        bool $trackedByExpiry,
        ?int $minShelfLifeAtReceiptDays = null,
        ?int $minShelfLifeAtShipmentDays = null,
    ): self {
        if ($trackedByExpiry && !$trackedByLot) {
            throw ExpiryRequiresLotTracking::create();
        }

        self::assertValidShelfLife('minShelfLifeAtReceiptDays', $minShelfLifeAtReceiptDays, $trackedByExpiry);
        self::assertValidShelfLife('minShelfLifeAtShipmentDays', $minShelfLifeAtShipmentDays, $trackedByExpiry);

        return new self($trackedByLot, $trackedByExpiry, $minShelfLifeAtReceiptDays, $minShelfLifeAtShipmentDays);
    }

    public static function none(): self
    {
        return new self(false, false, null, null);
    }

    private static function assertValidShelfLife(string $field, ?int $days, bool $trackedByExpiry): void
    {
        if (null === $days) {
            return;
        }

        if (!$trackedByExpiry || $days < 0) {
            throw InvalidShelfLife::forField($field, $days, $trackedByExpiry);
        }
    }
}
