<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Http\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Post;
use App\Inbound\Domain\Receipt;
use App\Inbound\Domain\ReceiptStatus;
use App\Inbound\Infrastructure\Http\Input\OpenReceiptInput;
use App\Inbound\Infrastructure\Http\Input\ReceiveItemInput;
use App\Inbound\Infrastructure\Http\State\ApproveOverReceiptProcessor;
use App\Inbound\Infrastructure\Http\State\CloseReceiptProcessor;
use App\Inbound\Infrastructure\Http\State\OpenReceiptProcessor;
use App\Inbound\Infrastructure\Http\State\ReceiptProvider;
use App\Inbound\Infrastructure\Http\State\ReceiveItemProcessor;

/**
 * Operators open, count and close receipts. Approving an over-receipt needs a supervisor (INB-08):
 * see `access_control` in `config/packages/security.yaml`.
 */
#[ApiResource(
    shortName: 'Receipt',
    operations: [
        new Post(
            uriTemplate: '/asns/{asnId}/receipts',
            uriVariables: ['asnId' => new Link(fromClass: AsnResource::class)],
            input: OpenReceiptInput::class,
            processor: OpenReceiptProcessor::class,
        ),
        new Get(uriTemplate: '/receipts/{id}', provider: ReceiptProvider::class),
        new Post(
            uriTemplate: '/receipts/{id}/items',
            status: 200,
            input: ReceiveItemInput::class,
            read: false,
            processor: ReceiveItemProcessor::class,
        ),
        new Post(
            uriTemplate: '/receipts/{id}/approve-over-receipt',
            status: 200,
            input: false,
            read: false,
            deserialize: false,
            processor: ApproveOverReceiptProcessor::class,
        ),
        new Post(
            uriTemplate: '/receipts/{id}/close',
            status: 200,
            input: false,
            read: false,
            deserialize: false,
            processor: CloseReceiptProcessor::class,
        ),
    ],
)]
final readonly class ReceiptResource
{
    /**
     * @param list<ExpectedItemResource> $expected
     * @param list<ReceivedLineResource> $lines
     * @param list<DiscrepancyResource>  $discrepancies
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $asnId,
        public string $dockLocationId,
        public ReceiptStatus $status,
        public int $overReceivedUnits,
        public bool $overReceiptApproved,
        public string $openedAt,
        public ?string $closedAt,
        public array $expected,
        public array $lines,
        public array $discrepancies,
    ) {
    }

    public static function fromDomain(Receipt $receipt): self
    {
        return new self(
            $receipt->id()->value,
            $receipt->asnId()->value,
            $receipt->dockLocationId()->value,
            $receipt->status(),
            $receipt->overReceivedUnits(),
            $receipt->isOverReceiptApproved(),
            $receipt->openedAt()->format(\DATE_ATOM),
            $receipt->closedAt()?->format(\DATE_ATOM),
            array_map(ExpectedItemResource::fromDomain(...), $receipt->expected()),
            array_map(ReceivedLineResource::fromDomain(...), $receipt->lines()),
            array_map(DiscrepancyResource::fromDomain(...), $receipt->discrepancies()),
        );
    }
}
