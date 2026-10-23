<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Outbound\Application\GetPickList\GetPickList;
use App\Outbound\Application\ListPickLists\ListPickLists;
use App\Outbound\Infrastructure\Http\Resource\PickListResource;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Infrastructure\Http\UriVariable;
use Symfony\Component\HttpFoundation\Request;

/**
 * Reads pick lists. The collection is the tenant's lists, the latest first, with the optional filter
 * `waveId`.
 *
 * @implements ProviderInterface<PickListResource>
 */
final readonly class PickListProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus)
    {
    }

    /**
     * @return PickListResource|list<PickListResource>
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PickListResource|array
    {
        if (!$operation instanceof CollectionOperationInterface) {
            return PickListResource::fromDomain($this->queryBus->ask(new GetPickList(UriVariable::string($uriVariables, 'id'))));
        }

        $request = $context['request'] ?? null;
        \assert($request instanceof Request);

        $pickLists = $this->queryBus->ask(new ListPickLists($request->query->has('waveId') ? $request->query->getString('waveId') : null));

        return array_map(PickListResource::fromDomain(...), $pickLists);
    }
}
