<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Inbound\Application\ReceiveItem\ReceiveItem;
use App\Inbound\Infrastructure\Http\Input\ReceiveItemInput;
use App\Inbound\Infrastructure\Http\Resource\ReceiptResource;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Infrastructure\Http\UriVariable;

/**
 * @implements ProcessorInterface<ReceiveItemInput, ReceiptResource>
 */
final readonly class ReceiveItemProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ReceiptResource
    {
        return ReceiptResource::fromDomain($this->commandBus->dispatch(new ReceiveItem(
            UriVariable::string($uriVariables, 'id'),
            $data->gtin,
            $data->units,
            $data->lot,
            $data->expiryDate,
            $data->condition,
        )));
    }
}
