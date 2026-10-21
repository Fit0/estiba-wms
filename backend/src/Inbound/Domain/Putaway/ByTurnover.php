<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Putaway;

use App\Shared\Domain\TurnoverClass;

/**
 * Keeps the fast movers close: among the free locations, in the order of the picking route, an A
 * product takes the first, a C product the last and a B product the one in the middle. Pickers
 * then walk less for the products they pick most.
 */
final readonly class ByTurnover implements PutawayStrategy
{
    #[\Override]
    public function choose(PutawayProduct $product, array $candidates): ?PutawayCandidate
    {
        $free = array_values(array_filter($candidates, static fn (PutawayCandidate $candidate): bool => $candidate->isFree()));
        if ([] === $free) {
            return null;
        }

        $last = \count($free) - 1;

        return $free[match ($product->turnoverClass) {
            TurnoverClass::A => 0,
            TurnoverClass::B => intdiv($last, 2),
            TurnoverClass::C => $last,
        }];
    }
}
