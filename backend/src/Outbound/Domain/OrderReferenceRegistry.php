<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

use App\Shared\Domain\TenantId;

/**
 * Whether a tenant already has an order with a reference (OUT-01). Registering an order asks it,
 * so the rule lives in the domain and a unit test can fake the answer.
 */
interface OrderReferenceRegistry
{
    public function isReferenceTaken(TenantId $tenantId, OrderReference $reference): bool;
}
