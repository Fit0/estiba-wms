<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Infrastructure\Http\UriVariable;
use App\Warehouse\Application\DefineZone\DefineZone;
use App\Warehouse\Infrastructure\Http\Input\DefineZoneInput;
use App\Warehouse\Infrastructure\Http\Resource\ZoneResource;

/**
 * @implements ProcessorInterface<DefineZoneInput, ZoneResource>
 */
final readonly class DefineZoneProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ZoneResource
    {
        $warehouse = $this->commandBus->dispatch(new DefineZone(
            UriVariable::string($uriVariables, 'warehouseId'),
            $data->code,
            $data->name,
            $data->storageClass,
            $data->pickSequence,
        ));

        $zones = $warehouse->zones();
        $zone = end($zones);
        if (false === $zone) {
            throw new \LogicException('The zone was just defined.');
        }

        return ZoneResource::fromDomain($warehouse->id(), $zone);
    }
}
