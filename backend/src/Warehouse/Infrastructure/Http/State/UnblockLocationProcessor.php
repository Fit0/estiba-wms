<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Shared\Infrastructure\Http\UriVariable;
use App\Warehouse\Application\UnblockLocation\UnblockLocation;
use App\Warehouse\Application\UnblockLocation\UnblockLocationHandler;
use App\Warehouse\Infrastructure\Http\Resource\LocationResource;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<null, LocationResource>
 */
final readonly class UnblockLocationProcessor implements ProcessorInterface
{
    public function __construct(
        private UnblockLocationHandler $handler,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LocationResource
    {
        $location = ($this->handler)(new UnblockLocation(UriVariable::string($uriVariables, 'id')));
        $this->entityManager->flush();

        return LocationResource::fromDomain($location);
    }
}
