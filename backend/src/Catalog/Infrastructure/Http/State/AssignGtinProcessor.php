<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Application\AssignGtin\AssignGtin;
use App\Catalog\Application\AssignGtin\AssignGtinHandler;
use App\Catalog\Infrastructure\Http\Input\AssignGtinInput;
use App\Catalog\Infrastructure\Http\Resource\ProductResource;
use App\Shared\Infrastructure\Http\UriVariable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<AssignGtinInput, ProductResource>
 */
final readonly class AssignGtinProcessor implements ProcessorInterface
{
    public function __construct(
        private AssignGtinHandler $handler,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProductResource
    {
        $product = ($this->handler)(new AssignGtin(UriVariable::string($uriVariables, 'id'), $data->gtin));
        $this->entityManager->flush();

        return ProductResource::fromDomain($product);
    }
}
