<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\Resource;

use App\Outbound\Domain\Parcel;

final readonly class ParcelResource
{
    /**
     * @param list<ParcelContentResource> $contents
     */
    public function __construct(
        public string $sscc,
        public int $weightGrams,
        public int $units,
        public array $contents,
        public string $closedAt,
    ) {
    }

    public static function fromDomain(Parcel $parcel): self
    {
        return new self(
            $parcel->sscc->value,
            $parcel->weightGrams,
            $parcel->totalUnits(),
            array_map(ParcelContentResource::fromDomain(...), $parcel->contents),
            $parcel->closedAt->format(\DATE_ATOM),
        );
    }
}
