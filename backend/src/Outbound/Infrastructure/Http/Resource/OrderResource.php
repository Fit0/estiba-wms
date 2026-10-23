<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Outbound\Domain\Order;
use App\Outbound\Domain\OrderPriority;
use App\Outbound\Domain\OrderStatus;
use App\Outbound\Infrastructure\Http\Input\RegisterOrderInput;
use App\Outbound\Infrastructure\Http\State\AllocateOrderProcessor;
use App\Outbound\Infrastructure\Http\State\CancelOrderProcessor;
use App\Outbound\Infrastructure\Http\State\OrderProvider;
use App\Outbound\Infrastructure\Http\State\RegisterOrderProcessor;

#[ApiResource(
    shortName: 'Order',
    operations: [
        new GetCollection(uriTemplate: '/orders', provider: OrderProvider::class),
        new Get(uriTemplate: '/orders/{id}', provider: OrderProvider::class),
        new Post(uriTemplate: '/orders', input: RegisterOrderInput::class, processor: RegisterOrderProcessor::class),
        new Post(
            uriTemplate: '/orders/{id}/allocate',
            status: 200,
            input: false,
            read: false,
            deserialize: false,
            processor: AllocateOrderProcessor::class,
        ),
        new Post(
            uriTemplate: '/orders/{id}/cancel',
            status: 200,
            input: false,
            read: false,
            deserialize: false,
            processor: CancelOrderProcessor::class,
        ),
    ],
)]
final readonly class OrderResource
{
    /**
     * @param list<OrderLineResource> $lines
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $reference,
        public string $warehouseId,
        public string $customer,
        public ShipToResource $shipTo,
        public OrderPriority $priority,
        public string $cutOffAt,
        public string $registeredAt,
        public OrderStatus $status,
        public int $shortageUnits,
        public array $lines,
    ) {
    }

    public static function fromDomain(Order $order): self
    {
        return new self(
            $order->id()->value,
            $order->reference()->value,
            $order->warehouseId()->value,
            $order->customer()->value,
            ShipToResource::fromDomain($order->shipTo()),
            $order->priority(),
            $order->cutOffAt()->format(\DATE_ATOM),
            $order->registeredAt()->format(\DATE_ATOM),
            $order->status(),
            $order->shortageUnits(),
            array_map(OrderLineResource::fromDomain(...), $order->lines()),
        );
    }
}
