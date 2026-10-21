<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Http\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Inbound\Domain\PutawayTask;
use App\Inbound\Domain\PutawayTaskStatus;
use App\Inbound\Infrastructure\Http\Input\ConfirmPutawayInput;
use App\Inbound\Infrastructure\Http\Input\SplitPutawayTaskInput;
use App\Inbound\Infrastructure\Http\State\ConfirmPutawayProcessor;
use App\Inbound\Infrastructure\Http\State\PutawayTaskProvider;
use App\Inbound\Infrastructure\Http\State\SplitPutawayTaskProcessor;

/**
 * The operators' work after a receipt closes. They confirm and split tasks; see `access_control`
 * in `config/packages/security.yaml`.
 */
#[ApiResource(
    shortName: 'PutawayTask',
    operations: [
        new GetCollection(uriTemplate: '/putaway-tasks', provider: PutawayTaskProvider::class),
        new Get(uriTemplate: '/putaway-tasks/{id}', provider: PutawayTaskProvider::class),
        new Post(
            uriTemplate: '/putaway-tasks/{id}/confirm',
            status: 200,
            input: ConfirmPutawayInput::class,
            read: false,
            processor: ConfirmPutawayProcessor::class,
        ),
        new Post(
            uriTemplate: '/putaway-tasks/{id}/split',
            input: SplitPutawayTaskInput::class,
            read: false,
            processor: SplitPutawayTaskProcessor::class,
        ),
    ],
)]
final readonly class PutawayTaskResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $receiptId,
        public string $reference,
        public string $productId,
        public int $units,
        public ?string $lot,
        public ?string $expiryDate,
        public bool $quarantine,
        public string $sourceLocationId,
        public ?string $suggestedLocationId,
        public PutawayTaskStatus $status,
        public ?string $confirmedLocationId,
        public ?string $overrideReason,
        public string $createdAt,
        public ?string $confirmedAt,
    ) {
    }

    public static function fromDomain(PutawayTask $task): self
    {
        $goods = $task->goods();

        return new self(
            $task->id()->value,
            $task->receiptId()->value,
            $task->reference()->value,
            $goods->productId->value,
            $goods->units,
            $goods->lot?->value,
            $goods->expiryDate?->format('Y-m-d'),
            $goods->quarantine,
            $task->sourceLocationId()->value,
            $task->suggestedLocationId()?->value,
            $task->status(),
            $task->confirmedLocationId()?->value,
            $task->overrideReason()?->value,
            $task->createdAt()->format(\DATE_ATOM),
            $task->confirmedAt()?->format(\DATE_ATOM),
        );
    }
}
