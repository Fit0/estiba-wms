<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Identity\Application\GetUser\GetUser;
use App\Identity\Application\ListUsers\ListUsers;
use App\Identity\Infrastructure\Http\Resource\UserResource;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Domain\PageRequest;
use App\Shared\Infrastructure\Http\UriVariable;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProviderInterface<UserResource>
 */
final readonly class UserProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus)
    {
    }

    /**
     * @return UserResource|TraversablePaginator<UserResource>
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): UserResource|TraversablePaginator
    {
        if (!$operation instanceof CollectionOperationInterface) {
            return UserResource::fromDomain($this->queryBus->ask(new GetUser(UriVariable::string($uriVariables, 'id'))));
        }

        $request = $context['request'] ?? null;
        \assert($request instanceof Request);

        $page = $this->queryBus->ask(new ListUsers(
            PageRequest::of($request->query->getInt('page', 1), $request->query->getInt('itemsPerPage', PageRequest::DEFAULT_ITEMS_PER_PAGE)),
        ));

        return new TraversablePaginator(
            new \ArrayIterator(array_map(UserResource::fromDomain(...), $page->items)),
            $page->request->page,
            $page->request->itemsPerPage,
            $page->total,
        );
    }
}
