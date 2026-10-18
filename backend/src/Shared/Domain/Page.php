<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * One page of a list and the total number of items across all pages.
 *
 * @template T
 */
final readonly class Page
{
    /**
     * @param list<T> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public PageRequest $request,
    ) {
    }
}
