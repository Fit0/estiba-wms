<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Outbound\Application\GetShipment\GetShipment;
use App\Outbound\Application\ListShipments\ListShipments;
use App\Outbound\Domain\ShipmentStatus;
use App\Outbound\Infrastructure\Http\Resource\ShipmentResource;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Domain\PageRequest;
use App\Shared\Infrastructure\Http\UriVariable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Lists the tenant's shipments, paged, the latest first, with the optional filter `status`.
 *
 * @implements ProviderInterface<ShipmentResource>
 */
final readonly class ShipmentProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus)
    {
    }

    /**
     * @return ShipmentResource|TraversablePaginator<ShipmentResource>
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ShipmentResource|TraversablePaginator
    {
        if (!$operation instanceof CollectionOperationInterface) {
            return ShipmentResource::fromDomain($this->queryBus->ask(new GetShipment(UriVariable::string($uriVariables, 'id'))));
        }

        $request = $context['request'] ?? null;
        \assert($request instanceof Request);

        $page = $this->queryBus->ask(new ListShipments(
            PageRequest::of($request->query->getInt('page', 1), $request->query->getInt('itemsPerPage', PageRequest::DEFAULT_ITEMS_PER_PAGE)),
            $this->statusFilter($request),
        ));

        return new TraversablePaginator(
            new \ArrayIterator(array_map(ShipmentResource::fromDomain(...), $page->items)),
            $page->request->page,
            $page->request->itemsPerPage,
            $page->total,
        );
    }

    private function statusFilter(Request $request): ?ShipmentStatus
    {
        if (!$request->query->has('status')) {
            return null;
        }

        return ShipmentStatus::tryFrom($request->query->getString('status'))
            ?? throw new BadRequestHttpException('The status filter must be packing or packed.');
    }
}
