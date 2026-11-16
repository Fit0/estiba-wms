<?php

declare(strict_types=1);

namespace App\Identity\Application\Contract;

use App\Shared\Domain\TenantId;

/**
 * How the other contexts put a name to a user id they hold, such as the audit trail showing who did
 * something (ADR 0059). It reads and changes nothing, and only sees the users of the tenant it is asked
 * about.
 */
interface UserDirectory
{
    /**
     * The email address of each user of the tenant among the ones asked for. A user that does not exist
     * in the tenant is left out, so the caller decides what to show for it.
     *
     * @param list<string> $userIds
     *
     * @return array<string, string> the email address by user id
     */
    public function emailsOf(TenantId $tenantId, array $userIds): array;
}
