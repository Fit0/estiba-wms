<?php

declare(strict_types=1);

namespace App\Tests\Unit\Outbound\Domain;

use App\Outbound\Domain\CustomerName;
use App\Outbound\Domain\Order;
use App\Outbound\Domain\OrderableProduct;
use App\Outbound\Domain\OrderedItem;
use App\Outbound\Domain\OrderId;
use App\Outbound\Domain\OrderPriority;
use App\Outbound\Domain\OrderReference;
use App\Outbound\Domain\ProductId;
use App\Outbound\Domain\ReservationId;
use App\Outbound\Domain\ShippingAddress;
use App\Outbound\Domain\WarehouseId;
use App\Shared\Domain\TenantId;
use App\Tests\Unit\Support\Ids;

/**
 * Builds valid objects so each test only spells out what it is about.
 */
final class OutboundMother
{
    public static function at(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-23T10:00:00+00:00');
    }

    public static function tenantId(): TenantId
    {
        return TenantId::fromString(Ids::next());
    }

    public static function productId(): ProductId
    {
        return ProductId::fromString(Ids::next());
    }

    public static function reservationId(): ReservationId
    {
        return ReservationId::fromString(Ids::next());
    }

    public static function address(): ShippingAddress
    {
        return ShippingAddress::of('Calle Mayor 12', '50001', 'Zaragoza', 'ES');
    }

    public static function item(int $units = 10, bool $available = true, ?ProductId $productId = null): OrderedItem
    {
        return new OrderedItem(new OrderableProduct($productId ?? self::productId(), $available), $units);
    }

    /**
     * A registered order with the given items, or two lines of 10 and 4 units, with its registration event pending.
     *
     * @param list<OrderedItem>|null $items
     */
    public static function order(?array $items = null, ?TenantId $tenantId = null, string $reference = 'PED-2027-0001', ?InMemoryOrderReferences $registry = null): Order
    {
        return Order::register(
            OrderId::fromString(Ids::next()),
            $tenantId ?? self::tenantId(),
            OrderReference::fromString($reference),
            WarehouseId::fromString(Ids::next()),
            CustomerName::fromString('Marea Moda Zaragoza'),
            self::address(),
            OrderPriority::Normal,
            new \DateTimeImmutable('2026-10-24T14:00:00+00:00'),
            $items ?? [self::item(10), self::item(4)],
            $registry ?? new InMemoryOrderReferences(),
            self::at(),
        );
    }
}
