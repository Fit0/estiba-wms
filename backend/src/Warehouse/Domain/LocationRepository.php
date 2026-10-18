<?php

declare(strict_types=1);

namespace App\Warehouse\Domain;

/**
 * There is no delete method on purpose: locations are blocked, never deleted, because
 * stock history keeps pointing at them (WH-07).
 */
interface LocationRepository extends LocationCodeRegistry
{
    public function find(LocationId $id): ?Location;

    public function save(Location $location): void;
}
