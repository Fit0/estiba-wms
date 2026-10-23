<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Outbound\Domain\PickList;
use App\Outbound\Domain\PickListStatus;
use App\Outbound\Infrastructure\Http\State\PickListProvider;

#[ApiResource(
    shortName: 'PickList',
    operations: [
        // Filtered by `waveId`. A pick list is made by releasing a wave, never directly.
        new GetCollection(uriTemplate: '/pick-lists', provider: PickListProvider::class),
        new Get(uriTemplate: '/pick-lists/{id}', provider: PickListProvider::class),
    ],
)]
final readonly class PickListResource
{
    /**
     * @param list<PickLineResource> $lines the stops, in the order of the walk
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $waveId,
        public PickListStatus $status,
        public int $unitsToPick,
        public array $lines,
        public string $createdAt,
    ) {
    }

    public static function fromDomain(PickList $pickList): self
    {
        return new self(
            $pickList->id()->value,
            $pickList->waveId()->value,
            $pickList->status(),
            $pickList->unitsToPick(),
            array_map(PickLineResource::fromDomain(...), $pickList->lines()),
            $pickList->createdAt()->format(\DATE_ATOM),
        );
    }
}
