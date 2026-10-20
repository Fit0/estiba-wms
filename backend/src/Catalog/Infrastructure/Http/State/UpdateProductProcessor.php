<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Application\UpdateProductDetails\UpdateProductDetails;
use App\Catalog\Infrastructure\Http\Input\UpdateProductInput;
use App\Catalog\Infrastructure\Http\Resource\ProductResource;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Infrastructure\Http\UriVariable;

/**
 * @implements ProcessorInterface<UpdateProductInput, ProductResource>
 */
final readonly class UpdateProductProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProductResource
    {
        return ProductResource::fromDomain($this->commandBus->dispatch(new UpdateProductDetails(
            UriVariable::string($uriVariables, 'id'),
            $data->name,
            $data->lengthMm,
            $data->widthMm,
            $data->heightMm,
            $data->weightGrams,
        )));
    }
}
