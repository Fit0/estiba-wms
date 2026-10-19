<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Http\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Warehouse\Domain\Location;
use App\Warehouse\Domain\LocationStatus;
use App\Warehouse\Domain\LocationType;
use App\Warehouse\Infrastructure\Http\Input\BlockLocationInput;
use App\Warehouse\Infrastructure\Http\Input\DefineLocationInput;
use App\Warehouse\Infrastructure\Http\State\BlockLocationProcessor;
use App\Warehouse\Infrastructure\Http\State\DefineLocationProcessor;
use App\Warehouse\Infrastructure\Http\State\LocationProvider;
use App\Warehouse\Infrastructure\Http\State\UnblockLocationProcessor;

#[ApiResource(
    shortName: 'Location',
    operations: [
        new GetCollection(
            uriTemplate: '/warehouses/{warehouseId}/locations',
            uriVariables: ['warehouseId' => new Link(fromClass: WarehouseResource::class)],
            provider: LocationProvider::class,
        ),
        new Post(
            uriTemplate: '/warehouses/{warehouseId}/locations',
            uriVariables: ['warehouseId' => new Link(fromClass: WarehouseResource::class)],
            input: DefineLocationInput::class,
            processor: DefineLocationProcessor::class,
        ),
        new Get(uriTemplate: '/locations/{id}', provider: LocationProvider::class),
        new Post(
            uriTemplate: '/locations/{id}/block',
            status: 200,
            input: BlockLocationInput::class,
            read: false,
            processor: BlockLocationProcessor::class,
        ),
        new Post(
            uriTemplate: '/locations/{id}/unblock',
            status: 200,
            input: false,
            read: false,
            deserialize: false,
            processor: UnblockLocationProcessor::class,
        ),
    ],
)]
final readonly class LocationResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $warehouseId,
        public string $zoneId,
        public string $code,
        public LocationType $type,
        public int $pickSequence,
        public LocationStatus $status,
        public ?string $blockReason,
        public ?int $maxWeightKg,
        public ?int $maxVolumeLitres,
        public ?int $maxPalletPositions,
    ) {
    }

    public static function fromDomain(Location $location): self
    {
        $capacity = $location->capacity();

        return new self(
            $location->id()->value,
            $location->warehouseId()->value,
            $location->zoneId()->value,
            $location->code()->value,
            $location->type(),
            $location->pickSequence()->value,
            $location->status(),
            $location->blockReason()?->value,
            $capacity->maxWeightKg,
            $capacity->maxVolumeLitres,
            $capacity->maxPalletPositions,
        );
    }
}
