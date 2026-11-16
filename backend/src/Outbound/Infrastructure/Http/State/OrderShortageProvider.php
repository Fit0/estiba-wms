<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Outbound\Application\GetOrderShortages\GetOrderShortages;
use App\Outbound\Infrastructure\Http\Resource\OrderShortageResource;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Infrastructure\Http\UriVariable;

/**
 * The lines of an order that are not fully reserved, each with why.
 *
 * @implements ProviderInterface<OrderShortageResource>
 */
final readonly class OrderShortageProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus)
    {
    }

    /**
     * @return list<OrderShortageResource>
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $shortages = $this->queryBus->ask(new GetOrderShortages(UriVariable::string($uriVariables, 'orderId')));

        return array_map(OrderShortageResource::fromQuery(...), $shortages);
    }
}
