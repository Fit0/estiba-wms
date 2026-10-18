<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Http\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Shared\Domain\PageRequest;
use App\Shared\Infrastructure\Http\UriVariable;
use App\Warehouse\Application\ListLocations\ListLocations;
use App\Warehouse\Application\ListLocations\ListLocationsHandler;
use App\Warehouse\Application\LocationLookup;
use App\Warehouse\Domain\LocationStatus;
use App\Warehouse\Infrastructure\Http\Resource\LocationResource;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Lists a warehouse's locations, paged, with the optional filters `zoneId` and `status`.
 *
 * @implements ProviderInterface<LocationResource>
 */
final readonly class LocationProvider implements ProviderInterface
{
    public function __construct(
        private LocationLookup $lookup,
        private ListLocationsHandler $listLocations,
    ) {
    }

    /**
     * @return LocationResource|TraversablePaginator<LocationResource>
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): LocationResource|TraversablePaginator
    {
        if (!$operation instanceof CollectionOperationInterface) {
            return LocationResource::fromDomain($this->lookup->get(UriVariable::string($uriVariables, 'id')));
        }

        $request = $context['request'] ?? null;
        \assert($request instanceof Request);

        $page = ($this->listLocations)(new ListLocations(
            UriVariable::string($uriVariables, 'warehouseId'),
            PageRequest::of($request->query->getInt('page', 1), $request->query->getInt('itemsPerPage', PageRequest::DEFAULT_ITEMS_PER_PAGE)),
            $request->query->has('zoneId') ? $request->query->getString('zoneId') : null,
            $this->statusFilter($request),
        ));

        return new TraversablePaginator(
            new \ArrayIterator(array_map(LocationResource::fromDomain(...), $page->items)),
            $page->request->page,
            $page->request->itemsPerPage,
            $page->total,
        );
    }

    private function statusFilter(Request $request): ?LocationStatus
    {
        if (!$request->query->has('status')) {
            return null;
        }

        return LocationStatus::tryFrom($request->query->getString('status'))
            ?? throw new BadRequestHttpException('The status filter must be "active" or "blocked".');
    }
}
