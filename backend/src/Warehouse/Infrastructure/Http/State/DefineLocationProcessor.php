<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Shared\Infrastructure\Http\UriVariable;
use App\Warehouse\Application\DefineLocation\DefineLocation;
use App\Warehouse\Application\DefineLocation\DefineLocationHandler;
use App\Warehouse\Infrastructure\Http\Input\DefineLocationInput;
use App\Warehouse\Infrastructure\Http\Resource\LocationResource;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<DefineLocationInput, LocationResource>
 */
final readonly class DefineLocationProcessor implements ProcessorInterface
{
    public function __construct(
        private DefineLocationHandler $handler,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LocationResource
    {
        $location = ($this->handler)(new DefineLocation(
            UriVariable::string($uriVariables, 'warehouseId'),
            $data->zoneId,
            $data->code,
            $data->type,
            $data->pickSequence,
            $data->maxWeightKg,
            $data->maxVolumeLitres,
            $data->maxPalletPositions,
        ));
        $this->entityManager->flush();

        return LocationResource::fromDomain($location);
    }
}
