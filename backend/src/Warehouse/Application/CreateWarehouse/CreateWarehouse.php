<?php

declare(strict_types=1);

namespace App\Warehouse\Application\CreateWarehouse;

use App\Shared\Application\Bus\Command;
use App\Warehouse\Domain\Warehouse;

/**
 * @implements Command<Warehouse>
 */
final readonly class CreateWarehouse implements Command
{
    public function __construct(
        public string $code,
        public string $name,
    ) {
    }
}
