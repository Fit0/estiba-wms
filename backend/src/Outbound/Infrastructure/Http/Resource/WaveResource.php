<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Outbound\Domain\OrderId;
use App\Outbound\Domain\PickListId;
use App\Outbound\Domain\Wave;
use App\Outbound\Domain\WaveStatus;
use App\Outbound\Infrastructure\Http\Input\CreateWaveInput;
use App\Outbound\Infrastructure\Http\State\CancelWaveProcessor;
use App\Outbound\Infrastructure\Http\State\CreateWaveProcessor;
use App\Outbound\Infrastructure\Http\State\ReleaseWaveProcessor;
use App\Outbound\Infrastructure\Http\State\WaveProvider;

#[ApiResource(
    shortName: 'Wave',
    operations: [
        new GetCollection(uriTemplate: '/waves', provider: WaveProvider::class),
        new Get(uriTemplate: '/waves/{id}', provider: WaveProvider::class),
        new Post(uriTemplate: '/waves', input: CreateWaveInput::class, processor: CreateWaveProcessor::class),
        // The body is optional: {"pickLists": 2} shares the wave among two operators, and none means one.
        new Post(
            uriTemplate: '/waves/{id}/release',
            status: 200,
            input: false,
            read: false,
            deserialize: false,
            processor: ReleaseWaveProcessor::class,
        ),
        new Post(
            uriTemplate: '/waves/{id}/cancel',
            status: 200,
            input: false,
            read: false,
            deserialize: false,
            processor: CancelWaveProcessor::class,
        ),
    ],
)]
final readonly class WaveResource
{
    /**
     * @param list<string> $orderIds
     * @param list<string> $pickListIds
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $warehouseId,
        public WaveStatus $status,
        public array $orderIds,
        public array $pickListIds,
        public string $createdAt,
        public ?string $releasedAt,
        public ?string $completedAt,
    ) {
    }

    public static function fromDomain(Wave $wave): self
    {
        return new self(
            $wave->id()->value,
            $wave->warehouseId()->value,
            $wave->status(),
            array_map(static fn (OrderId $id): string => $id->value, $wave->orderIds()),
            array_map(static fn (PickListId $id): string => $id->value, $wave->pickListIds()),
            $wave->createdAt()->format(\DATE_ATOM),
            $wave->releasedAt()?->format(\DATE_ATOM),
            $wave->completedAt()?->format(\DATE_ATOM),
        );
    }
}
