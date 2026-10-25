<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Http\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Audit\Application\ListAuditEntries\ListAuditEntries;
use App\Audit\Domain\AuditFilter;
use App\Audit\Infrastructure\Http\Resource\AuditEntryResource;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Domain\PageRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Lists the audit trail of the tenant, paged, the latest first, with the optional filters `eventType`,
 * `actorId`, `subjectId`, `from` (included) and `until` (excluded), the last two as dates with time.
 *
 * @implements ProviderInterface<AuditEntryResource>
 */
final readonly class AuditEntryProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus)
    {
    }

    /**
     * @return TraversablePaginator<AuditEntryResource>
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        $request = $context['request'] ?? null;
        \assert($request instanceof Request);

        $page = $this->queryBus->ask(new ListAuditEntries(
            PageRequest::of($request->query->getInt('page', 1), $request->query->getInt('itemsPerPage', PageRequest::DEFAULT_ITEMS_PER_PAGE)),
            new AuditFilter(
                $this->text($request, 'eventType'),
                $this->text($request, 'actorId'),
                $this->text($request, 'subjectId'),
                $this->moment($request, 'from'),
                $this->moment($request, 'until'),
            ),
        ));

        return new TraversablePaginator(
            new \ArrayIterator(array_map(AuditEntryResource::fromDomain(...), $page->items)),
            $page->request->page,
            $page->request->itemsPerPage,
            $page->total,
        );
    }

    private function text(Request $request, string $name): ?string
    {
        return $request->query->has($name) ? $request->query->getString($name) : null;
    }

    private function moment(Request $request, string $name): ?\DateTimeImmutable
    {
        if (!$request->query->has($name)) {
            return null;
        }

        $moment = \DateTimeImmutable::createFromFormat(\DATE_ATOM, $request->query->getString($name));

        return false === $moment
            ? throw new BadRequestHttpException(\sprintf('The "%s" filter must be a date with time in ISO 8601, such as 2026-10-24T08:00:00+00:00.', $name)) : $moment;
    }
}
