<?php

declare(strict_types=1);

namespace App\Inbound\Domain;

use App\Shared\Domain\TenantId;

/**
 * Lets {@see Asn::register()} check that a reference is free within the tenant (INB-03) without
 * knowing how ASNs are stored. A unique database constraint backs the check against races.
 */
interface AsnReferenceRegistry
{
    public function isReferenceTaken(TenantId $tenantId, AsnReference $reference): bool;
}
