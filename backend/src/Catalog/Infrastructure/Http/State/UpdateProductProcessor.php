<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Application\UpdateProductDetails\UpdateProductDetails;
use App\Catalog\Application\UpdateProductDetails\UpdateProductDetailsHandler;
use App\Catalog\Infrastructure\Http\Input\UpdateProductInput;
use App\Catalog\Infrastructure\Http\Resource\ProductResource;
use App\Shared\Infrastructure\Http\UriVariable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<UpdateProductInput, ProductResource>
 */
final readonly class UpdateProductProcessor implements ProcessorInterface
{
    public function __construct(
        private UpdateProductDetailsHandler $handler,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProductResource
    {
        $product = ($this->handler)(new UpdateProductDetails(
            UriVariable::string($uriVariables, 'id'),
            $data->name,
            $data->lengthMm,
            $data->widthMm,
            $data->heightMm,
            $data->weightGrams,
        ));
        $this->entityManager->flush();

        return ProductResource::fromDomain($product);
    }
}
