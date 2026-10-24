<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Outbound\Application\GetWave\GetWave;
use App\Outbound\Application\ListWaves\ListWaves;
use App\Outbound\Domain\WaveStatus;
use App\Outbound\Infrastructure\Http\Resource\WaveResource;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Domain\PageRequest;
use App\Shared\Infrastructure\Http\UriVariable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Lists the tenant's waves, paged, the latest first, with the optional filter `status`.
 *
 * @implements ProviderInterface<WaveResource>
 */
final readonly class WaveProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus)
    {
    }

    /**
     * @return WaveResource|TraversablePaginator<WaveResource>
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): WaveResource|TraversablePaginator
    {
        if (!$operation instanceof CollectionOperationInterface) {
            return WaveResource::fromDomain($this->queryBus->ask(new GetWave(UriVariable::string($uriVariables, 'id'))));
        }

        $request = $context['request'] ?? null;
        \assert($request instanceof Request);

        $page = $this->queryBus->ask(new ListWaves(
            PageRequest::of($request->query->getInt('page', 1), $request->query->getInt('itemsPerPage', PageRequest::DEFAULT_ITEMS_PER_PAGE)),
            $this->statusFilter($request),
        ));

        return new TraversablePaginator(
            new \ArrayIterator(array_map(WaveResource::fromDomain(...), $page->items)),
            $page->request->page,
            $page->request->itemsPerPage,
            $page->total,
        );
    }

    private function statusFilter(Request $request): ?WaveStatus
    {
        if (!$request->query->has('status')) {
            return null;
        }

        return WaveStatus::tryFrom($request->query->getString('status'))
            ?? throw new BadRequestHttpException('The status filter must be planned, released, completed or cancelled.');
    }
}
