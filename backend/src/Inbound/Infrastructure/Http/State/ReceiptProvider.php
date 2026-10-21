<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Inbound\Application\GetReceipt\GetReceipt;
use App\Inbound\Infrastructure\Http\Resource\ReceiptResource;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Infrastructure\Http\UriVariable;

/**
 * @implements ProviderInterface<ReceiptResource>
 */
final readonly class ReceiptProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus)
    {
    }

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ReceiptResource
    {
        return ReceiptResource::fromDomain($this->queryBus->ask(new GetReceipt(UriVariable::string($uriVariables, 'id'))));
    }
}
