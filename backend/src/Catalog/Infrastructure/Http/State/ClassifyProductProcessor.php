<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Application\ClassifyProduct\ClassifyProduct;
use App\Catalog\Infrastructure\Http\Input\ClassifyProductInput;
use App\Catalog\Infrastructure\Http\Resource\ProductResource;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Infrastructure\Http\UriVariable;

/**
 * @implements ProcessorInterface<ClassifyProductInput, ProductResource>
 */
final readonly class ClassifyProductProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProductResource
    {
        return ProductResource::fromDomain($this->commandBus->dispatch(new ClassifyProduct(
            UriVariable::string($uriVariables, 'id'),
            $data->family,
            $data->turnoverClass,
        )));
    }
}
