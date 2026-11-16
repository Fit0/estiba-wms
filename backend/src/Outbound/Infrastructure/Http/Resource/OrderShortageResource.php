<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use App\Outbound\Application\GetOrderShortages\LineShortage;
use App\Outbound\Infrastructure\Http\State\OrderShortageProvider;

/**
 * Why a line of an order is not fully reserved, as the stock is now (ADR 0058). A line with no
 * `obstacles` has no stock of the product anywhere. Each obstacle is units that exist but cannot be
 * promised, with the reason and, when it is about a place, where they are.
 */
#[ApiResource(
    shortName: 'OrderShortage',
    operations: [
        new GetCollection(
            uriTemplate: '/orders/{orderId}/shortages',
            uriVariables: ['orderId' => new Link(fromClass: OrderResource::class)],
            provider: OrderShortageProvider::class,
        ),
    ],
)]
final readonly class OrderShortageResource
{
    /**
     * @param list<ShortageObstacleResource> $obstacles
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public int $lineNumber,
        public string $productId,
        public int $orderedUnits,
        public int $allocatedUnits,
        public int $shortageUnits,
        public array $obstacles,
    ) {
    }

    public static function fromQuery(LineShortage $shortage): self
    {
        return new self(
            $shortage->lineNumber,
            $shortage->productId,
            $shortage->orderedUnits,
            $shortage->allocatedUnits,
            $shortage->shortageUnits,
            array_map(ShortageObstacleResource::fromContract(...), $shortage->obstacles),
        );
    }
}
