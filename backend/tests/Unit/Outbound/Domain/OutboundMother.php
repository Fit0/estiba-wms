<?php

declare(strict_types=1);

namespace App\Tests\Unit\Outbound\Domain;

use App\Outbound\Domain\CustomerName;
use App\Outbound\Domain\LineAllocation;
use App\Outbound\Domain\OperatorId;
use App\Outbound\Domain\Order;
use App\Outbound\Domain\OrderableProduct;
use App\Outbound\Domain\OrderedItem;
use App\Outbound\Domain\OrderId;
use App\Outbound\Domain\OrderPriority;
use App\Outbound\Domain\OrderReference;
use App\Outbound\Domain\PickLine;
use App\Outbound\Domain\PickList;
use App\Outbound\Domain\PickListId;
use App\Outbound\Domain\PickRequest;
use App\Outbound\Domain\ProductId;
use App\Outbound\Domain\ReservationId;
use App\Outbound\Domain\ScannedPick;
use App\Outbound\Domain\ShippingAddress;
use App\Outbound\Domain\WarehouseId;
use App\Outbound\Domain\WaveId;
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
     * Units to pick from a location, for the first line of a new order unless told otherwise.
     */
    public static function pickRequest(string $locationCode, int $zonePickSequence = 1, int $pickSequence = 10, int $orderLine = 1, ?OrderId $orderId = null, int $units = 1, ?string $lot = null, ?\DateTimeImmutable $expiryDate = null, ?string $stockItemId = null): PickRequest
    {
        return new PickRequest(
            $orderId ?? OrderId::fromString(Ids::next()),
            $orderLine,
            self::productId(),
            $stockItemId ?? Ids::next(),
            Ids::next(),
            $locationCode,
            $zonePickSequence,
            $pickSequence,
            $lot,
            $expiryDate,
            $units,
        );
    }

    public static function operatorId(): OperatorId
    {
        return OperatorId::fromString(Ids::next());
    }

    /**
     * A pick list for these picks, with its generation event pending.
     *
     * @param list<PickRequest> $route
     */
    public static function pickList(array $route): PickList
    {
        return PickList::generate(PickListId::fromString(Ids::next()), self::tenantId(), WaveId::fromString(Ids::next()), $route, self::at());
    }

    /**
     * What an operator would scan at a line: its location, the barcode of its product and its lot.
     */
    public static function scanFor(PickLine $line): ScannedPick
    {
        return new ScannedPick($line->locationCode, $line->productId, $line->lot);
    }

    /**
     * An order whose lines are all allocated, in a wave that is on the floor.
     *
     * @param list<int> $units the units of each line
     */
    public static function orderInWave(array $units, ?TenantId $tenantId = null): Order
    {
        $order = self::order(array_map(static fn (int $u): OrderedItem => self::item($u), $units), $tenantId);
        $order->recordAllocations(
            array_map(static fn (int $u, int $i): LineAllocation => new LineAllocation($i + 1, self::reservationId(), $u), $units, array_keys($units)),
            self::at(),
        );
        $order->joinWave(WaveId::fromString(Ids::next()));
        $order->pullEvents();

        return $order;
    }

    /**
     * An order whose lines were all picked in full, ready to pack. A line listed in `$short` had that
     * many units missing instead (the line number is its key).
     *
     * @param list<int>       $units the units of each line
     * @param array<int, int> $short units not found, by line number
     */
    public static function pickedOrder(array $units, array $short = [], ?TenantId $tenantId = null): Order
    {
        $order = self::orderInWave($units, $tenantId);
        foreach ($units as $index => $line) {
            $notFound = $short[$index + 1] ?? 0;
            $order->recordPick($index + 1, $line - $notFound, $notFound, self::at());
        }
        $order->pullEvents();

        return $order;
    }

    /**
     * A registered order with the given items, or two lines of 10 and 4 units, with its registration event pending.
     *
     * @param list<OrderedItem>|null $items
     */
    public static function order(?array $items = null, ?TenantId $tenantId = null, string $reference = 'PED-2027-0001', ?InMemoryOrderReferences $registry = null, ?WarehouseId $warehouseId = null): Order
    {
        return Order::register(
            OrderId::fromString(Ids::next()),
            $tenantId ?? self::tenantId(),
            OrderReference::fromString($reference),
            $warehouseId ?? WarehouseId::fromString(Ids::next()),
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
