<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Application\DefinePackagingLevel\DefinePackagingLevel;
use App\Catalog\Application\DefinePackagingLevel\DefinePackagingLevelHandler;
use App\Catalog\Infrastructure\Http\Input\DefinePackagingLevelInput;
use App\Catalog\Infrastructure\Http\Resource\ProductResource;
use App\Shared\Infrastructure\Http\UriVariable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<DefinePackagingLevelInput, ProductResource>
 */
final readonly class DefinePackagingLevelProcessor implements ProcessorInterface
{
    public function __construct(
        private DefinePackagingLevelHandler $handler,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProductResource
    {
        $product = ($this->handler)(new DefinePackagingLevel(UriVariable::string($uriVariables, 'id'), $data->type, $data->baseUnits));
        $this->entityManager->flush();

        return ProductResource::fromDomain($product);
    }
}
