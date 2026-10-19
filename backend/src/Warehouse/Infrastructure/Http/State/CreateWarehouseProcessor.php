<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Warehouse\Application\CreateWarehouse\CreateWarehouse;
use App\Warehouse\Application\CreateWarehouse\CreateWarehouseHandler;
use App\Warehouse\Infrastructure\Http\Input\CreateWarehouseInput;
use App\Warehouse\Infrastructure\Http\Resource\WarehouseResource;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Owns the transaction: it flushes after the use case, until the command bus does (E03-02).
 *
 * @implements ProcessorInterface<CreateWarehouseInput, WarehouseResource>
 */
final readonly class CreateWarehouseProcessor implements ProcessorInterface
{
    public function __construct(
        private CreateWarehouseHandler $handler,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): WarehouseResource
    {
        $warehouse = ($this->handler)(new CreateWarehouse($data->code, $data->name));
        $this->entityManager->flush();

        return WarehouseResource::fromDomain($warehouse);
    }
}
