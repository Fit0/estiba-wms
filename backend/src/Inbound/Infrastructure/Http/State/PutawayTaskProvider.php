<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Http\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Inbound\Application\GetPutawayTask\GetPutawayTask;
use App\Inbound\Application\ListPutawayTasks\ListPutawayTasks;
use App\Inbound\Domain\PutawayTaskStatus;
use App\Inbound\Infrastructure\Http\Resource\PutawayTaskResource;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Domain\PageRequest;
use App\Shared\Infrastructure\Http\UriVariable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Lists the tenant's putaway tasks, paged, oldest first, with the optional filters `receiptId` and
 * `status`.
 *
 * @implements ProviderInterface<PutawayTaskResource>
 */
final readonly class PutawayTaskProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus)
    {
    }

    /**
     * @return PutawayTaskResource|TraversablePaginator<PutawayTaskResource>
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PutawayTaskResource|TraversablePaginator
    {
        if (!$operation instanceof CollectionOperationInterface) {
            return PutawayTaskResource::fromDomain($this->queryBus->ask(new GetPutawayTask(UriVariable::string($uriVariables, 'id'))));
        }

        $request = $context['request'] ?? null;
        \assert($request instanceof Request);

        $page = $this->queryBus->ask(new ListPutawayTasks(
            PageRequest::of($request->query->getInt('page', 1), $request->query->getInt('itemsPerPage', PageRequest::DEFAULT_ITEMS_PER_PAGE)),
            $request->query->has('receiptId') ? $request->query->getString('receiptId') : null,
            $this->statusFilter($request),
        ));

        return new TraversablePaginator(
            new \ArrayIterator(array_map(PutawayTaskResource::fromDomain(...), $page->items)),
            $page->request->page,
            $page->request->itemsPerPage,
            $page->total,
        );
    }

    private function statusFilter(Request $request): ?PutawayTaskStatus
    {
        if (!$request->query->has('status')) {
            return null;
        }

        return PutawayTaskStatus::tryFrom($request->query->getString('status'))
            ?? throw new BadRequestHttpException('The status filter must be pending or confirmed.');
    }
}
