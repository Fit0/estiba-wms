<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Putaway;

/**
 * The first empty location, along the picking route, where the units fit. The simplest rule, and
 * the one the others fall back on.
 */
final readonly class FirstFreeLocation implements PutawayStrategy
{
    #[\Override]
    public function choose(PutawayProduct $product, array $candidates): ?PutawayCandidate
    {
        return array_find($candidates, static fn (PutawayCandidate $candidate): bool => $candidate->isFree());
    }
}
