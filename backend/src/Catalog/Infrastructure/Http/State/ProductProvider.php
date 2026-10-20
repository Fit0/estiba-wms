<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Application\GetProduct\GetProduct;
use App\Catalog\Application\ListProducts\ListProducts;
use App\Catalog\Domain\ProductStatus;
use App\Catalog\Infrastructure\Http\Resource\ProductResource;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Domain\PageRequest;
use App\Shared\Infrastructure\Http\UriVariable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Lists the tenant's products, paged, with the optional filters `sku` (starts with) and `status`.
 *
 * @implements ProviderInterface<ProductResource>
 */
final readonly class ProductProvider implements ProviderInterface
{
    public function __construct(
        private QueryBus $queryBus,
    ) {
    }

    /**
     * @return ProductResource|TraversablePaginator<ProductResource>
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ProductResource|TraversablePaginator
    {
        if (!$operation instanceof CollectionOperationInterface) {
            return ProductResource::fromDomain($this->queryBus->ask(new GetProduct(UriVariable::string($uriVariables, 'id'))));
        }

        $request = $context['request'] ?? null;
        \assert($request instanceof Request);

        $page = $this->queryBus->ask(new ListProducts(
            PageRequest::of($request->query->getInt('page', 1), $request->query->getInt('itemsPerPage', PageRequest::DEFAULT_ITEMS_PER_PAGE)),
            $request->query->has('sku') ? $request->query->getString('sku') : null,
            $this->statusFilter($request),
        ));

        return new TraversablePaginator(
            new \ArrayIterator(array_map(ProductResource::fromDomain(...), $page->items)),
            $page->request->page,
            $page->request->itemsPerPage,
            $page->total,
        );
    }

    private function statusFilter(Request $request): ?ProductStatus
    {
        if (!$request->query->has('status')) {
            return null;
        }

        return ProductStatus::tryFrom($request->query->getString('status'))
            ?? throw new BadRequestHttpException('The status filter must be "active", "blocked" or "discontinued".');
    }
}
