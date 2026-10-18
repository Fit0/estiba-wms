<?php

declare(strict_types=1);

namespace App\Warehouse\Domain;

interface WarehouseRepository extends WarehouseCodeRegistry
{
    public function find(WarehouseId $id): ?Warehouse;

    public function save(Warehouse $warehouse): void;
}
