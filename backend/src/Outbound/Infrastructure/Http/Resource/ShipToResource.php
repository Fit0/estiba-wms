<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\Resource;

use App\Outbound\Domain\ShippingAddress;

final readonly class ShipToResource
{
    public function __construct(
        public string $street,
        public string $postalCode,
        public string $city,
        public string $countryCode,
    ) {
    }

    public static function fromDomain(ShippingAddress $address): self
    {
        return new self($address->street, $address->postalCode, $address->city, $address->countryCode);
    }
}
