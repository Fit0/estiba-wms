<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Application\RegisterProduct\RegisterProduct;
use App\Catalog\Infrastructure\Http\Input\RegisterProductInput;
use App\Catalog\Infrastructure\Http\Resource\ProductResource;
use App\Shared\Application\Bus\CommandBus;

/**
 * @implements ProcessorInterface<RegisterProductInput, ProductResource>
 */
final readonly class RegisterProductProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProductResource
    {
        return ProductResource::fromDomain($this->commandBus->dispatch(new RegisterProduct(
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
            $data->family,
            $data->turnoverClass,
        )));
    }
}
