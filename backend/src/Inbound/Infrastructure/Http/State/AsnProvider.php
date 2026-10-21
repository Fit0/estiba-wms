<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Http\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Inbound\Application\GetAsn\GetAsn;
use App\Inbound\Application\ListAsns\ListAsns;
use App\Inbound\Domain\AsnStatus;
use App\Inbound\Infrastructure\Http\Resource\AsnResource;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Domain\PageRequest;
use App\Shared\Infrastructure\Http\UriVariable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Lists the tenant's ASNs, paged, the latest expected date first, with the optional filter
 * `status`.
 *
 * @implements ProviderInterface<AsnResource>
 */
final readonly class AsnProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus)
    {
    }

    /**
     * @return AsnResource|TraversablePaginator<AsnResource>
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AsnResource|TraversablePaginator
    {
        if (!$operation instanceof CollectionOperationInterface) {
            return AsnResource::fromDomain($this->queryBus->ask(new GetAsn(UriVariable::string($uriVariables, 'id'))));
        }

        $request = $context['request'] ?? null;
        \assert($request instanceof Request);

        $page = $this->queryBus->ask(new ListAsns(
            PageRequest::of($request->query->getInt('page', 1), $request->query->getInt('itemsPerPage', PageRequest::DEFAULT_ITEMS_PER_PAGE)),
            $this->statusFilter($request),
        ));

        return new TraversablePaginator(
            new \ArrayIterator(array_map(AsnResource::fromDomain(...), $page->items)),
            $page->request->page,
            $page->request->itemsPerPage,
            $page->total,
        );
    }

    private function statusFilter(Request $request): ?AsnStatus
    {
        if (!$request->query->has('status')) {
            return null;
        }

        return AsnStatus::tryFrom($request->query->getString('status'))
            ?? throw new BadRequestHttpException('The status filter must be expected, partially_received, received, closed or cancelled.');
    }
}
