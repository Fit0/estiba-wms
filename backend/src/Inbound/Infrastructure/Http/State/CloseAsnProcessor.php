<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Inbound\Application\CloseAsn\CloseAsn;
use App\Inbound\Infrastructure\Http\Resource\AsnResource;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Infrastructure\Http\UriVariable;

/**
 * @implements ProcessorInterface<null, AsnResource>
 */
final readonly class CloseAsnProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AsnResource
    {
        return AsnResource::fromDomain($this->commandBus->dispatch(new CloseAsn(UriVariable::string($uriVariables, 'id'))));
    }
}
