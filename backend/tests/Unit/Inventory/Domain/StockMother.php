<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Domain;

use App\Inventory\Domain\AuthorId;
use App\Inventory\Domain\LocationId;
use App\Inventory\Domain\LotNumber;
use App\Inventory\Domain\ProductId;
use App\Inventory\Domain\Quantity;
use App\Inventory\Domain\StockItem;
use App\Inventory\Domain\StockItemId;
use App\Inventory\Domain\StockKey;
use App\Inventory\Domain\StockMovementId;
use App\Inventory\Domain\StockStatus;
use App\Shared\Domain\TenantId;
use App\Tests\Unit\Support\Ids;

/**
 * Builds valid objects so each test only spells out what it is about.
 */
final class StockMother
{
    public static function at(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-21T10:00:00+00:00');
    }

    public static function tenantId(): TenantId
    {
        return TenantId::fromString(Ids::next());
    }

    public static function productId(): ProductId
    {
        return ProductId::fromString(Ids::next());
    }

    public static function author(): AuthorId
    {
        return AuthorId::fromString(Ids::next());
    }

    public static function movementId(): StockMovementId
    {
        return StockMovementId::fromString(Ids::next());
    }

    public static function key(?ProductId $productId = null, ?LocationId $locationId = null, StockStatus $status = StockStatus::Available, ?string $lot = null, ?\DateTimeImmutable $expiryDate = null): StockKey
    {
        return new StockKey(
            $productId ?? self::productId(),
            $locationId ?? LocationId::fromString(Ids::next()),
            null === $lot ? null : LotNumber::fromString($lot),
            $expiryDate,
            $status,
        );
    }

    /**
     * An empty stock item of an available product with no lot.
     */
    public static function stockItem(?TenantId $tenantId = null, ?StockKey $key = null): StockItem
    {
        return StockItem::open(StockItemId::fromString(Ids::next()), $tenantId ?? self::tenantId(), $key ?? self::key());
    }

    /**
     * A stock item that already holds the units, with the movement of its receipt already pulled.
     */
    public static function stockItemHolding(int $units, ?TenantId $tenantId = null, ?StockKey $key = null, ?\DateTimeImmutable $receivedAt = null): StockItem
    {
        $stockItem = self::stockItem($tenantId, $key);
        $stockItem->receive(self::movementId(), Quantity::of($units), null, self::author(), $receivedAt ?? self::at());
        $stockItem->pullMovements();
        $stockItem->pullEvents();

        return $stockItem;
    }
}
