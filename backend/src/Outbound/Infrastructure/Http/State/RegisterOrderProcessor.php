<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Outbound\Application\RegisterOrder\RegisterOrder;
use App\Outbound\Infrastructure\Http\Input\RegisterOrderInput;
use App\Outbound\Infrastructure\Http\Input\RegisterOrderLines;
use App\Outbound\Infrastructure\Http\Resource\OrderResource;
use App\Shared\Application\Bus\CommandBus;

/**
 * @implements ProcessorInterface<RegisterOrderInput, OrderResource>
 */
final readonly class RegisterOrderProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrderResource
    {
        return OrderResource::fromDomain($this->commandBus->dispatch(new RegisterOrder(
            $data->reference,
            $data->warehouseId,
            $data->customer,
            $data->shipTo->street,
            $data->shipTo->postalCode,
            $data->shipTo->city,
            $data->shipTo->countryCode,
            $data->priority,
            $data->cutOffAt,
            RegisterOrderLines::from($data->lines),
        )));
    }
}
