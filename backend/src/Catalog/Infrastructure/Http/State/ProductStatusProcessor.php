<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Application\ChangeProductStatus\ChangeProductStatus;
use App\Catalog\Domain\ProductStatus;
use App\Catalog\Infrastructure\Http\Resource\ProductResource;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Infrastructure\Http\UriVariable;

/**
 * Serves block, unblock and discontinue. Each operation says its target in the
 * `target_status` extra property of its definition.
 *
 * @implements ProcessorInterface<null, ProductResource>
 */
final readonly class ProductStatusProcessor implements ProcessorInterface
{
    public function __construct(private CommandBus $commandBus)
    {
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProductResource
    {
        $target = $operation->getExtraProperties()['target_status'] ?? null;
        \assert(\is_string($target));

        return ProductResource::fromDomain($this->commandBus->dispatch(new ChangeProductStatus(UriVariable::string($uriVariables, 'id'), ProductStatus::from($target))));
    }
}
