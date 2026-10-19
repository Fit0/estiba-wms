<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Http\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Shared\Domain\StorageClass;
use App\Warehouse\Domain\WarehouseId;
use App\Warehouse\Domain\Zone;
use App\Warehouse\Infrastructure\Http\Input\DefineZoneInput;
use App\Warehouse\Infrastructure\Http\State\DefineZoneProcessor;
use App\Warehouse\Infrastructure\Http\State\ZoneProvider;

#[ApiResource(
    shortName: 'Zone',
    operations: [
        new Get(uriTemplate: '/zones/{id}', provider: ZoneProvider::class),
        new Post(
            uriTemplate: '/warehouses/{warehouseId}/zones',
            uriVariables: ['warehouseId' => new Link(fromClass: WarehouseResource::class)],
            input: DefineZoneInput::class,
            processor: DefineZoneProcessor::class,
        ),
    ],
)]
final readonly class ZoneResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $warehouseId,
        public string $code,
        public string $name,
        public StorageClass $storageClass,
        public int $pickSequence,
    ) {
    }

    public static function fromDomain(WarehouseId $warehouseId, Zone $zone): self
    {
        return new self(
            $zone->id->value,
            $warehouseId->value,
            $zone->code->value,
            $zone->name->value,
            $zone->storageClass,
            $zone->pickSequence->value,
        );
    }
}
