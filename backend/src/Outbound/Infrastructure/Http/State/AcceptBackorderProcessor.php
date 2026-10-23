<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Outbound\Application\AcceptBackorder\AcceptBackorder;
use App\Outbound\Infrastructure\Http\Resource\OrderResource;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Infrastructure\Http\UriVariable;

/**
 * @implements ProcessorInterface<null, OrderResource>
 */
final readonly class AcceptBackorderProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrderResource
    {
        return OrderResource::fromDomain($this->commandBus->dispatch(new AcceptBackorder(UriVariable::string($uriVariables, 'id'))));
    }
}
