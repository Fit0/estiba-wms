<?php

declare(strict_types=1);

namespace App\Warehouse\Application\CreateWarehouse;

final readonly class CreateWarehouse
{
    public function __construct(
        public string $code,
        public string $name,
    ) {
    }
}
