<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

use App\Outbound\Domain\Exception\InvalidPickListCount;

/**
 * Shares the picks of a wave among the operators who will walk it, as pieces of the route that
 * follow one another: each operator takes a stretch of the walk, so two operators are never in the
 * same aisle at once unless the stretch is long.
 *
 * Every operator gets about the same number of picks, and the first ones get one more when they do
 * not divide evenly. There are never more lists than picks.
 */
final class PickListSplitter
{
    /**
     * @param list<PickRequest> $route the picks in the order of the walk
     *
     * @return list<non-empty-list<PickRequest>>
     *
     * @throws InvalidPickListCount when the count is below one or above the limit
     */
    public function split(array $route, int $lists): array
    {
        if ($lists < 1 || $lists > InvalidPickListCount::MAX) {
            throw InvalidPickListCount::forCount($lists);
        }

        $lists = min($lists, \count($route));
        if (0 === $lists) {
            return [];
        }

        $size = intdiv(\count($route), $lists);
        $extra = \count($route) % $lists;

        $pieces = [];
        $offset = 0;
        for ($i = 0; $i < $lists; ++$i) {
            $length = $size + ($i < $extra ? 1 : 0);
            $piece = \array_slice($route, $offset, $length);
            $offset += $length;

            if ([] !== $piece) {
                $pieces[] = $piece;
            }
        }

        return $pieces;
    }
}
