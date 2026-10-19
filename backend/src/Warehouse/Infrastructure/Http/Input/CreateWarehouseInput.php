<?php

declare(strict_types=1);

namespace App\Warehouse\Infrastructure\Http\Input;

final readonly class CreateWarehouseInput
{
    public function __construct(
        public string $code,
        public string $name,
    ) {
    }
}
