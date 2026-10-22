<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Inventory\Application\GetProductStock\GetProductStock;
use App\Inventory\Infrastructure\Http\Resource\ProductStockResource;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Infrastructure\Http\UriVariable;

/**
 * @implements ProviderInterface<ProductStockResource>
 */
final readonly class ProductStockProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus)
    {
    }

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ProductStockResource
    {
        return ProductStockResource::fromStock($this->queryBus->ask(new GetProductStock(UriVariable::string($uriVariables, 'productId'))));
    }
}
