<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Application\AssignGtin\AssignGtin;
use App\Catalog\Infrastructure\Http\Input\AssignGtinInput;
use App\Catalog\Infrastructure\Http\Resource\ProductResource;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Infrastructure\Http\UriVariable;

/**
 * @implements ProcessorInterface<AssignGtinInput, ProductResource>
 */
final readonly class AssignGtinProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProductResource
    {
        return ProductResource::fromDomain($this->commandBus->dispatch(new AssignGtin(UriVariable::string($uriVariables, 'id'), $data->gtin)));
    }
}
