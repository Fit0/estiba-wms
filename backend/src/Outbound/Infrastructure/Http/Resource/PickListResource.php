<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Outbound\Domain\PickList;
use App\Outbound\Domain\PickListStatus;
use App\Outbound\Infrastructure\Http\Input\AssignPickListInput;
use App\Outbound\Infrastructure\Http\Input\ConfirmPickLineInput;
use App\Outbound\Infrastructure\Http\State\AssignPickListProcessor;
use App\Outbound\Infrastructure\Http\State\ConfirmPickLineProcessor;
use App\Outbound\Infrastructure\Http\State\PickListProvider;
use App\Outbound\Infrastructure\Http\State\StartPickListProcessor;

#[ApiResource(
    shortName: 'PickList',
    operations: [
        // Filtered by `waveId`. A pick list is made by releasing a wave, never directly.
        new GetCollection(uriTemplate: '/pick-lists', provider: PickListProvider::class),
        new Get(uriTemplate: '/pick-lists/{id}', provider: PickListProvider::class),
        new Post(uriTemplate: '/pick-lists/{id}/assign', status: 200, input: AssignPickListInput::class, read: false, processor: AssignPickListProcessor::class),
        new Post(uriTemplate: '/pick-lists/{id}/start', status: 200, input: false, read: false, deserialize: false, processor: StartPickListProcessor::class),
        new Post(uriTemplate: '/pick-lists/{id}/lines/{position}/confirm', status: 200, input: ConfirmPickLineInput::class, read: false, processor: ConfirmPickLineProcessor::class),
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
        public ?string $operatorId,
        public int $unitsToPick,
        public int $unitsPicked,
        public array $lines,
        public string $createdAt,
        public ?string $startedAt,
        public ?string $completedAt,
    ) {
    }

    public static function fromDomain(PickList $pickList): self
    {
        return new self(
            $pickList->id()->value,
            $pickList->waveId()->value,
            $pickList->status(),
            $pickList->operatorId()?->value,
            $pickList->unitsToPick(),
            $pickList->unitsPicked(),
            array_map(PickLineResource::fromDomain(...), $pickList->lines()),
            $pickList->createdAt()->format(\DATE_ATOM),
            $pickList->startedAt()?->format(\DATE_ATOM),
            $pickList->completedAt()?->format(\DATE_ATOM),
        );
    }
}
