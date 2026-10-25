<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\Input;

/**
 * The carrier that takes the parcels, and the number it gave to follow them, if it gave one.
 */
final readonly class DispatchShipmentInput
{
    public function __construct(
        public string $carrier,
        public ?string $trackingNumber = null,
    ) {
    }
}
