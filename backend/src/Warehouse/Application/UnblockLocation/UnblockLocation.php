<?php

declare(strict_types=1);

namespace App\Warehouse\Application\UnblockLocation;

use App\Shared\Application\Bus\Command;
use App\Warehouse\Domain\Location;

/**
 * @implements Command<Location>
 */
final readonly class UnblockLocation implements Command
{
    public function __construct(public string $locationId)
    {
    }
}
