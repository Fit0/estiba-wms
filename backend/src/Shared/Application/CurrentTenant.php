<?php

declare(strict_types=1);

namespace App\Shared\Application;

use App\Shared\Domain\TenantId;

/**
 * The tenant on whose behalf the current request acts. A missing tenant is an error, never
 * "all tenants".
 */
interface CurrentTenant
{
    /**
     * @throws TenantNotResolved
     */
    public function id(): TenantId;
}
