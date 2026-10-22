<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Inventory\Application\StockOverview\StockMovementEntry;
use App\Inventory\Domain\MovementType;
use App\Inventory\Domain\StockStatus;
use App\Inventory\Infrastructure\Http\State\StockMovementProvider;

/**
 * The history of the stock: one line of the ledger, newest first, filtered by `stockItemId`,
 * `productId` and `locationId`. `change` is negative when units left; `balanceAfter` is what the
 * stock item held afterwards.
 */
#[ApiResource(
    shortName: 'StockMovement',
    operations: [
        new GetCollection(uriTemplate: '/stock-movements', provider: StockMovementProvider::class),
    ],
)]
final readonly class StockMovementResource
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $stockItemId,
        public string $productId,
        public string $sku,
        public string $locationId,
        public string $locationCode,
        public ?string $lot,
        public ?string $expiryDate,
        public StockStatus $status,
        public MovementType $type,
        public int $change,
        public int $balanceAfter,
        public ?string $reference,
        public ?string $reason,
        public string $authorId,
        public string $occurredAt,
    ) {
    }

    public static function fromEntry(StockMovementEntry $entry): self
    {
        return new self(
            $entry->id,
            $entry->stockItemId,
            $entry->productId,
            $entry->sku,
            $entry->locationId,
            $entry->locationCode,
            $entry->lot,
            $entry->expiryDate?->format('Y-m-d'),
            $entry->status,
            $entry->type,
            $entry->change,
            $entry->balanceAfter,
            $entry->reference,
            $entry->reason,
            $entry->authorId,
            $entry->occurredAt->format(\DATE_ATOM),
        );
    }
}
