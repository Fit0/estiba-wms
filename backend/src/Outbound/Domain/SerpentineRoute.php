<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

/**
 * Puts picks in the order of a serpentine walk, so the operator goes up one aisle, comes down the
 * next and never backtracks (OUT-09).
 *
 * The walk goes zone by zone, in the order of their pick sequence, and aisle by aisle inside each
 * zone, in alphabetical order. The first aisle is walked in the order of the pick sequence of its
 * locations, the second in the opposite order, the third in the first order again, and so on. The
 * aisles counted are those that have something to pick, so an aisle with nothing to pick does not
 * turn the next one around.
 *
 * Picks from the same location are kept together and ordered by order and line, so the same
 * requests always give the same route.
 */
final class SerpentineRoute
{
    /**
     * @param list<PickRequest> $requests
     *
     * @return list<PickRequest>
     */
    public function order(array $requests): array
    {
        $route = [];
        $walkingUp = true;

        foreach ($this->aislesInWalkingOrder($requests) as $aisle) {
            usort($aisle, $this->byPlaceInAisle(...));

            foreach ($walkingUp ? $aisle : array_reverse($aisle) as $request) {
                $route[] = $request;
            }

            $walkingUp = !$walkingUp;
        }

        return $route;
    }

    /**
     * The requests grouped by aisle, the aisles in the order they are walked.
     *
     * @param list<PickRequest> $requests
     *
     * @return list<list<PickRequest>>
     */
    private function aislesInWalkingOrder(array $requests): array
    {
        $groups = [];
        foreach ($requests as $request) {
            $groups[\sprintf('%010d|%02d%s', $request->zonePickSequence, \strlen($request->aisle()), $request->aisle())][] = $request;
        }

        ksort($groups, \SORT_STRING);

        return array_values($groups);
    }

    private function byPlaceInAisle(PickRequest $a, PickRequest $b): int
    {
        return [$a->pickSequence, $a->locationCode, $a->orderId->value, $a->orderLineNumber]
            <=> [$b->pickSequence, $b->locationCode, $b->orderId->value, $b->orderLineNumber];
    }
}
