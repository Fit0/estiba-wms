<?php

declare(strict_types=1);

namespace App\Outbound\Application\RegisterOrder;

use App\Outbound\Application\OrderableProducts;
use App\Outbound\Domain\CustomerName;
use App\Outbound\Domain\Exception\UnknownShipFromWarehouse;
use App\Outbound\Domain\Order;
use App\Outbound\Domain\OrderedItem;
use App\Outbound\Domain\OrderId;
use App\Outbound\Domain\OrderReference;
use App\Outbound\Domain\OrderRepository;
use App\Outbound\Domain\ShippingAddress;
use App\Outbound\Domain\WarehouseId;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Application\Clock;
use App\Shared\Application\CurrentTenant;
use App\Shared\Application\IdGenerator;
use App\Warehouse\Application\Contract\WarehouseDirectory;

final readonly class RegisterOrderHandler implements CommandHandler
{
    public function __construct(
        private OrderRepository $orders,
        private OrderableProducts $products,
        private WarehouseDirectory $warehouses,
        private CurrentTenant $tenant,
        private EventBus $events,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    public function __invoke(RegisterOrder $command): Order
    {
        $warehouse = $this->warehouses->findById($this->tenant->id(), $command->warehouseId)
            ?? throw UnknownShipFromWarehouse::forId($command->warehouseId);

        $order = Order::register(
            OrderId::fromString($this->ids->next()),
            $this->tenant->id(),
            OrderReference::fromString($command->reference),
            WarehouseId::fromString($warehouse->id),
            CustomerName::fromString($command->customer),
            ShippingAddress::of($command->street, $command->postalCode, $command->city, $command->countryCode),
            $command->priority,
            $command->cutOffAt,
            array_map($this->item(...), $command->lines),
            $this->orders,
            $this->clock->now(),
        );

        $this->orders->save($order);
        $this->events->publish(...$order->pullEvents());

        return $order;
    }

    private function item(RegisterOrderLine $line): OrderedItem
    {
        return new OrderedItem($this->products->byId($line->productId), $line->units);
    }
}
