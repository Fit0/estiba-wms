<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Shared\Infrastructure\Http\UriVariable;
use App\Warehouse\Application\BlockLocation\BlockLocation;
use App\Warehouse\Application\BlockLocation\BlockLocationHandler;
use App\Warehouse\Infrastructure\Http\Input\BlockLocationInput;
use App\Warehouse\Infrastructure\Http\Resource\LocationResource;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<BlockLocationInput, LocationResource>
 */
final readonly class BlockLocationProcessor implements ProcessorInterface
{
    public function __construct(
        private BlockLocationHandler $handler,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LocationResource
    {
        $location = ($this->handler)(new BlockLocation(UriVariable::string($uriVariables, 'id'), $data->reason));
        $this->entityManager->flush();

        return LocationResource::fromDomain($location);
    }
}
