<?php

declare(strict_types=1);

namespace App\Warehouse\Application\BlockLocation;

use App\Shared\Application\Bus\Command;
use App\Warehouse\Domain\Location;

/**
 * @implements Command<Location>
 */
final readonly class BlockLocation implements Command
{
    public function __construct(
        public string $locationId,
        public string $reason,
    ) {
    }
}
