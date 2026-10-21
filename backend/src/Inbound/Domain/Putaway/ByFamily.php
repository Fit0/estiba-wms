<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Putaway;

use App\Shared\Domain\ProductFamily;

/**
 * Keeps each family together: the first free location in a zone that keeps the product's family.
 * A product without a family, or one whose family has no free room left, goes to the first free
 * location, so the goods never stay at the dock for want of a rule.
 */
final readonly class ByFamily implements PutawayStrategy
{
    public function __construct(private PutawayStrategy $fallback = new FirstFreeLocation())
    {
    }

    #[\Override]
    public function choose(PutawayProduct $product, array $candidates): ?PutawayCandidate
    {
        $family = $product->family;

        if ($family instanceof ProductFamily) {
            $inItsZone = array_find($candidates, static fn (PutawayCandidate $candidate): bool => $candidate->isFree() && $candidate->keeps($family));
            if (null !== $inItsZone) {
                return $inItsZone;
            }
        }

        return $this->fallback->choose($product, $candidates);
    }
}
