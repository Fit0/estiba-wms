<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Which page of a list is wanted. Out-of-range values are brought back into range, so a
 * request for page 0 or 5000 items per page still gets a sensible answer.
 */
final readonly class PageRequest
{
    public const int DEFAULT_ITEMS_PER_PAGE = 30;
    public const int MAX_ITEMS_PER_PAGE = 100;

    private function __construct(
        public int $page,
        public int $itemsPerPage,
    ) {
    }

    public static function of(int $page = 1, int $itemsPerPage = self::DEFAULT_ITEMS_PER_PAGE): self
    {
        return new self(max(1, $page), min(self::MAX_ITEMS_PER_PAGE, max(1, $itemsPerPage)));
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->itemsPerPage;
    }
}
