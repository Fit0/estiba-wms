<?php

declare(strict_types=1);

namespace App\Outbound\Application\GetParcelLabel;

use App\Shared\Application\Bus\Query;

/**
 * The content of the label of one parcel of a shipment.
 *
 * @implements Query<ParcelLabel>
 */
final readonly class GetParcelLabel implements Query
{
    public function __construct(
        public string $shipmentId,
        public string $sscc,
    ) {
    }
}
