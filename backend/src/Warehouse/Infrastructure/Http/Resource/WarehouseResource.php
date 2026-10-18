<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Http\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Warehouse\Domain\Warehouse;
use App\Warehouse\Domain\Zone;
use App\Warehouse\Infrastructure\Http\Input\CreateWarehouseInput;
use App\Warehouse\Infrastructure\Http\State\CreateWarehouseProcessor;
use App\Warehouse\Infrastructure\Http\State\WarehouseProvider;

#[ApiResource(
    shortName: 'Warehouse',
    operations: [
        new GetCollection(uriTemplate: '/warehouses', paginationEnabled: false, provider: WarehouseProvider::class),
        new Get(uriTemplate: '/warehouses/{id}', provider: WarehouseProvider::class),
        new Post(uriTemplate: '/warehouses', input: CreateWarehouseInput::class, processor: CreateWarehouseProcessor::class),
    ],
)]
final readonly class WarehouseResource
{
    /**
     * @param list<ZoneResource> $zones
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $code,
        public string $name,
        public array $zones,
    ) {
    }

    public static function fromDomain(Warehouse $warehouse): self
    {
        return new self(
            $warehouse->id()->value,
            $warehouse->code()->value,
            $warehouse->name()->value,
            array_map(static fn (Zone $zone): ZoneResource => ZoneResource::fromDomain($warehouse->id(), $zone), $warehouse->zones()),
        );
    }
}
