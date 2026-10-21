<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Putaway;

/**
 * Proposes where received goods should go. The strategies are interchangeable: each one is a rule
 * of thumb a warehouse can choose, and they all choose among the same candidates (ADR 0011).
 *
 * A strategy only suggests. The operator may put the goods elsewhere, giving a reason (INB-13).
 */
interface PutawayStrategy
{
    /**
     * @param list<PutawayCandidate> $candidates in the order of the picking route
     *
     * @return PutawayCandidate|null null when no candidate is free
     */
    public function choose(PutawayProduct $product, array $candidates): ?PutawayCandidate;
}
