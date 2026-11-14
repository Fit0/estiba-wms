<?php

declare(strict_types=1);

namespace App\Erp\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Erp\Application\ListConfirmations\ListConfirmations;
use App\Erp\Application\OutboxStatus;
use App\Erp\Infrastructure\Http\Resource\ErpConfirmationResource;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Domain\PageRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Lists the confirmations to the ERP, paged, the latest first, with the optional filter `status`.
 *
 * @implements ProviderInterface<ErpConfirmationResource>
 */
final readonly class ErpConfirmationProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus)
    {
    }

    /**
     * @return TraversablePaginator<ErpConfirmationResource>
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        $request = $context['request'] ?? null;
        \assert($request instanceof Request);

        $page = $this->queryBus->ask(new ListConfirmations(
            PageRequest::of($request->query->getInt('page', 1), $request->query->getInt('itemsPerPage', PageRequest::DEFAULT_ITEMS_PER_PAGE)),
            $this->statusFilter($request),
        ));

        return new TraversablePaginator(
            new \ArrayIterator(array_map(ErpConfirmationResource::fromApplication(...), $page->items)),
            $page->request->page,
            $page->request->itemsPerPage,
            $page->total,
        );
    }

    private function statusFilter(Request $request): ?OutboxStatus
    {
        if (!$request->query->has('status')) {
            return null;
        }

        return OutboxStatus::tryFrom($request->query->getString('status'))
            ?? throw new BadRequestHttpException('The status filter must be pending, sent or failed.');
    }
}
