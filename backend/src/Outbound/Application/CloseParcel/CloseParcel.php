<?php

declare(strict_types=1);

namespace App\Outbound\Application\CloseParcel;

use App\Outbound\Domain\Shipment;
use App\Shared\Application\Bus\Command;

/**
 * Seals a parcel of a shipment with what is inside and what it weighs. Closing it assigns its SSCC.
 *
 * @implements Command<Shipment>
 */
final readonly class CloseParcel implements Command
{
    /**
     * @param list<CloseParcelContent> $contents
     */
    public function __construct(
        public string $shipmentId,
        public array $contents,
        public int $weightGrams,
    ) {
    }
}
