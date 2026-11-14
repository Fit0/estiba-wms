<?php

declare(strict_types=1);

namespace App\Erp\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Erp\Application\RetryConfirmation\RetryConfirmation;
use App\Erp\Infrastructure\Http\Resource\ErpConfirmationResource;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Infrastructure\Http\UriVariable;

/**
 * Puts a failed confirmation back to be sent.
 *
 * @implements ProcessorInterface<null, ErpConfirmationResource>
 */
final readonly class ErpConfirmationProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ErpConfirmationResource
    {
        return ErpConfirmationResource::fromApplication($this->commandBus->dispatch(new RetryConfirmation(UriVariable::string($uriVariables, 'shipmentId'))));
    }
}
