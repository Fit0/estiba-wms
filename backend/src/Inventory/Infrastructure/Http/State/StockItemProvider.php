<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Inventory\Application\GetStockItem\GetStockItem;
use App\Inventory\Application\ListStock\ListStock;
use App\Inventory\Domain\StockStatus;
use App\Inventory\Infrastructure\Http\Resource\StockItemResource;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Infrastructure\Http\UriVariable;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Lists the tenant's stock, paged, with the optional filters `productId`, `locationId`,
 * `warehouseId` and `status`, or shows one stock item.
 *
 * @implements ProviderInterface<StockItemResource>
 */
final readonly class StockItemProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus)
    {
    }

    /**
     * @return StockItemResource|TraversablePaginator<StockItemResource>
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): StockItemResource|TraversablePaginator
    {
        if (!$operation instanceof CollectionOperationInterface) {
            return StockItemResource::fromEntry($this->queryBus->ask(new GetStockItem(UriVariable::string($uriVariables, 'id'))));
        }

        $filters = QueryFilters::of($context);
        $page = $this->queryBus->ask(new ListStock(
            $filters->page(),
            $filters->text('productId'),
            $filters->text('locationId'),
            $filters->text('warehouseId'),
            $this->status($filters->text('status')),
        ));

        return new TraversablePaginator(
            new \ArrayIterator(array_map(StockItemResource::fromEntry(...), $page->items)),
            $page->request->page,
            $page->request->itemsPerPage,
            $page->total,
        );
    }

    private function status(?string $value): ?StockStatus
    {
        if (null === $value) {
            return null;
        }

        return StockStatus::tryFrom($value)
            ?? throw new BadRequestHttpException('The status filter must be available, quarantine, damaged or on_hold.');
    }
}
