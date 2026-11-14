<?php

declare(strict_types=1);

namespace App\Erp\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Erp\Application\ImportResult;
use App\Erp\Application\ListImports\ListImports;
use App\Erp\Infrastructure\Http\Resource\ErpImportResource;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Domain\PageRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Lists the files the import dealt with, paged, the latest first, with the optional filter `result`.
 *
 * @implements ProviderInterface<ErpImportResource>
 */
final readonly class ErpImportProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus)
    {
    }

    /**
     * @return TraversablePaginator<ErpImportResource>
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        $request = $context['request'] ?? null;
        \assert($request instanceof Request);

        $page = $this->queryBus->ask(new ListImports(
            PageRequest::of($request->query->getInt('page', 1), $request->query->getInt('itemsPerPage', PageRequest::DEFAULT_ITEMS_PER_PAGE)),
            $this->resultFilter($request),
        ));

        return new TraversablePaginator(
            new \ArrayIterator(array_map(ErpImportResource::fromApplication(...), $page->items)),
            $page->request->page,
            $page->request->itemsPerPage,
            $page->total,
        );
    }

    private function resultFilter(Request $request): ?ImportResult
    {
        if (!$request->query->has('result')) {
            return null;
        }

        return ImportResult::tryFrom($request->query->getString('result'))
            ?? throw new BadRequestHttpException('The result filter must be processed or rejected.');
    }
}
