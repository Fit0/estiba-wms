<?php

declare(strict_types=1);

namespace App\Erp\Infrastructure\Http\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Erp\Application\OutboxEntry;
use App\Erp\Application\OutboxStatus;
use App\Erp\Application\ShippedLine;
use App\Erp\Infrastructure\Http\State\ErpConfirmationProcessor;
use App\Erp\Infrastructure\Http\State\ErpConfirmationProvider;

/**
 * The confirmation of a shipment on its way to the ERP: what it says, where it is and why it failed, if it did (ADR 0050).
 */
#[ApiResource(
    shortName: 'ErpConfirmation',
    operations: [
        new GetCollection(uriTemplate: '/erp/confirmations', provider: ErpConfirmationProvider::class),
        new Post(
            uriTemplate: '/erp/confirmations/{shipmentId}/retry',
            status: 200,
            input: false,
            read: false,
            deserialize: false,
            processor: ErpConfirmationProcessor::class,
        ),
    ],
)]
final readonly class ErpConfirmationResource
{
    /**
     * @param list<array{sku: string, units: int}> $lines
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $shipmentId,
        public string $orderReference,
        public OutboxStatus $status,
        public int $attempts,
        public string $carrier,
        public ?string $trackingNumber,
        public int $parcels,
        public array $lines,
        public string $shippedAt,
        public string $nextAttemptAt,
        public ?string $lastError,
        public string $createdAt,
        public ?string $sentAt,
    ) {
    }

    public static function fromApplication(OutboxEntry $entry): self
    {
        $confirmation = $entry->confirmation;

        return new self(
            $entry->shipmentId,
            $confirmation->orderReference,
            $entry->status,
            $entry->attempts,
            $confirmation->carrier,
            $confirmation->trackingNumber,
            $confirmation->parcels,
            array_map(static fn (ShippedLine $line): array => ['sku' => $line->sku, 'units' => $line->units], $confirmation->lines),
            $confirmation->shippedAt->format(\DATE_ATOM),
            $entry->nextAttemptAt->format(\DATE_ATOM),
            $entry->lastError,
            $entry->createdAt->format(\DATE_ATOM),
            $entry->sentAt?->format(\DATE_ATOM),
        );
    }
}
