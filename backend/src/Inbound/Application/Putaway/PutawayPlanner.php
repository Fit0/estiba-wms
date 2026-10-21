<?php

declare(strict_types=1);

namespace App\Inbound\Application\Putaway;

use App\Catalog\Application\Contract\ProductCatalog;
use App\Inbound\Domain\AsnReference;
use App\Inbound\Domain\Exception\ProductNotReceivable;
use App\Inbound\Domain\Putaway\PutawayCandidate;
use App\Inbound\Domain\Putaway\PutawayProduct;
use App\Inbound\Domain\Putaway\PutawayStrategyName;
use App\Inbound\Domain\PutawayTask;
use App\Inbound\Domain\PutawayTaskId;
use App\Inbound\Domain\Receipt;
use App\Shared\Application\IdGenerator;

/**
 * Plans one putaway task for each kind of goods a receipt brought, at the location the configured
 * strategy suggests (ADR 0011). Two kinds of goods are never sent to the same empty location.
 */
final readonly class PutawayPlanner
{
    public function __construct(
        private PutawayLocations $locations,
        private ProductCatalog $catalog,
        private IdGenerator $ids,
        private PutawayStrategyName $strategy,
    ) {
    }

    /**
     * @return list<PutawayTask>
     */
    public function plan(Receipt $receipt, AsnReference $reference, \DateTimeImmutable $at): array
    {
        $strategy = $this->strategy->strategy();
        $taken = [];
        $tasks = [];

        foreach ($receipt->receivedGoods() as $goods) {
            $product = $this->catalog->findById($receipt->tenantId(), $goods->productId->value)
                ?? throw ProductNotReceivable::unknown($goods->productId->value);

            $candidates = $this->locations->candidatesFor($receipt->tenantId(), $receipt->dockLocationId(), $product, $goods, $taken);
            $suggestion = $strategy->choose(new PutawayProduct($goods->productId, $product->family, $product->turnoverClass), $candidates);
            if ($suggestion instanceof PutawayCandidate) {
                $taken[] = $suggestion->locationId->value;
            }

            $tasks[] = PutawayTask::plan(
                PutawayTaskId::fromString($this->ids->next()),
                $receipt->tenantId(),
                $receipt->id(),
                $reference,
                $goods,
                $receipt->dockLocationId(),
                $suggestion?->locationId,
                $at,
            );
        }

        return $tasks;
    }
}
