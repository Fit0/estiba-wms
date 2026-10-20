<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Shared\Domain\TenantId;

interface TenantRepository
{
    public function find(TenantId $id): ?Tenant;

    public function save(Tenant $tenant): void;
}
