<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Inventory\Application\ListStockMovements\ListStockMovements;
use App\Inventory\Infrastructure\Http\Resource\StockMovementResource;
use App\Shared\Application\Bus\QueryBus;

/**
 * Lists the tenant's movements, paged, newest first, with the optional filters `stockItemId`,
 * `productId` and `locationId`.
 *
 * @implements ProviderInterface<StockMovementResource>
 */
final readonly class StockMovementProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus)
    {
    }

    /**
     * @return TraversablePaginator<StockMovementResource>
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        $filters = QueryFilters::of($context);
        $page = $this->queryBus->ask(new ListStockMovements(
            $filters->page(),
            $filters->text('stockItemId'),
            $filters->text('productId'),
            $filters->text('locationId'),
        ));

        return new TraversablePaginator(
            new \ArrayIterator(array_map(StockMovementResource::fromEntry(...), $page->items)),
            $page->request->page,
            $page->request->itemsPerPage,
            $page->total,
        );
    }
}
