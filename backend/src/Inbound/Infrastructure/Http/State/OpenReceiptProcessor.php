<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Inbound\Application\OpenReceipt\OpenReceipt;
use App\Inbound\Infrastructure\Http\Input\OpenReceiptInput;
use App\Inbound\Infrastructure\Http\Resource\ReceiptResource;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Infrastructure\Http\UriVariable;

/**
 * @implements ProcessorInterface<OpenReceiptInput, ReceiptResource>
 */
final readonly class OpenReceiptProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ReceiptResource
    {
        return ReceiptResource::fromDomain($this->commandBus->dispatch(new OpenReceipt(
            UriVariable::string($uriVariables, 'asnId'),
            $data->dockLocationId,
        )));
    }
}
