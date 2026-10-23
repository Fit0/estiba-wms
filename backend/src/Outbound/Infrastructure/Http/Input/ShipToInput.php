<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\Input;

final readonly class ShipToInput
{
    public function __construct(
        public string $street,
        public string $postalCode,
        public string $city,
        public string $countryCode,
    ) {
    }
}
