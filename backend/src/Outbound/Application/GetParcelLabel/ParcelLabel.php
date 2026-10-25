<?php

declare(strict_types=1);

namespace App\Outbound\Application\GetParcelLabel;

/**
 * What goes on the label of a parcel, before it is drawn in any format.
 */
final readonly class ParcelLabel
{
    public function __construct(
        public string $sscc,
        public string $orderReference,
        public string $customer,
        public string $street,
        public string $postalCode,
        public string $city,
        public string $countryCode,
        public int $parcelNumber,
        public int $weightGrams,
        public int $units,
        public \DateTimeImmutable $closedAt,
    ) {
    }
}
