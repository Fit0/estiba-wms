<?php

declare(strict_types=1);

namespace App\Warehouse\Application\Contract;

/**
 * What another context may know about a warehouse, at the moment it asked. It is a copy: changing
 * the warehouse later does not change it.
 */
final readonly class WarehouseSnapshot
{
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
    ) {
    }
}
