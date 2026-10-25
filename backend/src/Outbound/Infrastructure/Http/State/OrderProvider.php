<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Outbound\Application\GetOrder\GetOrder;
use App\Outbound\Application\ListOrders\ListOrders;
use App\Outbound\Domain\OrderStatus;
use App\Outbound\Infrastructure\Http\Resource\OrderResource;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Domain\PageRequest;
use App\Shared\Infrastructure\Http\UriVariable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Lists the tenant's orders, paged, the most urgent first, with the optional filter `status`.
 *
 * @implements ProviderInterface<OrderResource>
 */
final readonly class OrderProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus)
    {
    }

    /**
     * @return OrderResource|TraversablePaginator<OrderResource>
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): OrderResource|TraversablePaginator
    {
        if (!$operation instanceof CollectionOperationInterface) {
            return OrderResource::fromDomain($this->queryBus->ask(new GetOrder(UriVariable::string($uriVariables, 'id'))));
        }

        $request = $context['request'] ?? null;
        \assert($request instanceof Request);

        $page = $this->queryBus->ask(new ListOrders(
            PageRequest::of($request->query->getInt('page', 1), $request->query->getInt('itemsPerPage', PageRequest::DEFAULT_ITEMS_PER_PAGE)),
            $this->statusFilter($request),
        ));

        return new TraversablePaginator(
            new \ArrayIterator(array_map(OrderResource::fromDomain(...), $page->items)),
            $page->request->page,
            $page->request->itemsPerPage,
            $page->total,
        );
    }

    private function statusFilter(Request $request): ?OrderStatus
    {
        if (!$request->query->has('status')) {
            return null;
        }

        return OrderStatus::tryFrom($request->query->getString('status'))
            ?? throw new BadRequestHttpException('The status filter must be registered, partially_allocated, allocated, in_wave, picked, shipped or cancelled.');
    }
}
