<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Shared\Domain\TenantId;

/**
 * How many users of a tenant can administer it right now, so a rule that must leave at least one of them
 * (IDN-11) can look at the other users without holding them. A unique constraint cannot back it, so two
 * requests at the same moment are settled by the transaction.
 */
interface ActiveAdmins
{
    /**
     * The users of the tenant that are active and have the admin role.
     */
    public function countIn(TenantId $tenantId): int;
}
