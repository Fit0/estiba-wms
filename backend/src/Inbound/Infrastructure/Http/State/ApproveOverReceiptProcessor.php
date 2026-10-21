<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Inbound\Application\ApproveOverReceipt\ApproveOverReceipt;
use App\Inbound\Infrastructure\Http\Resource\ReceiptResource;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Infrastructure\Http\UriVariable;

/**
 * @implements ProcessorInterface<null, ReceiptResource>
 */
final readonly class ApproveOverReceiptProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ReceiptResource
    {
        return ReceiptResource::fromDomain($this->commandBus->dispatch(new ApproveOverReceipt(UriVariable::string($uriVariables, 'id'))));
    }
}
