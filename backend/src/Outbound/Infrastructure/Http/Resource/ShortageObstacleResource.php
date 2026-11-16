<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http\Resource;

use App\Inventory\Application\Contract\UnreservableReason;
use App\Inventory\Application\Contract\UnreservableStock;

final readonly class ShortageObstacleResource
{
    /**
     * @param list<string> $locationCodes
     */
    public function __construct(
        public UnreservableReason $reason,
        public int $units,
        public array $locationCodes,
    ) {
    }

    public static function fromContract(UnreservableStock $stock): self
    {
        return new self($stock->reason, $stock->units, $stock->locationCodes);
    }
}
