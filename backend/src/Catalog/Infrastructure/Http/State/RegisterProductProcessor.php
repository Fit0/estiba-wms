<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Application\RegisterProduct\RegisterProduct;
use App\Catalog\Application\RegisterProduct\RegisterProductHandler;
use App\Catalog\Infrastructure\Http\Input\RegisterProductInput;
use App\Catalog\Infrastructure\Http\Resource\ProductResource;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Owns the transaction: it flushes after the use case, until the command bus does (E03-02).
 *
 * @implements ProcessorInterface<RegisterProductInput, ProductResource>
 */
final readonly class RegisterProductProcessor implements ProcessorInterface
{
    public function __construct(
        private RegisterProductHandler $handler,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProductResource
    {
        $product = ($this->handler)(new RegisterProduct(
            $data->sku,
            $data->name,
            $data->baseUnit,
            $data->storageClass,
            $data->lengthMm,
            $data->widthMm,
            $data->heightMm,
            $data->weightGrams,
            $data->trackedByLot,
            $data->trackedByExpiry,
            $data->minShelfLifeAtReceiptDays,
            $data->minShelfLifeAtShipmentDays,
        ));
        $this->entityManager->flush();

        return ProductResource::fromDomain($product);
    }
}
