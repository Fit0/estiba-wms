<?php

declare(strict_types=1);

namespace App\Shared\Application;

use App\Shared\Domain\TenantId;

/**
 * Runs a piece of work for a tenant when nobody is signed in: a job that a schedule starts, not a
 * request of a user. Everything that asks for the {@see CurrentTenant} while the work runs gets this one.
 */
interface TenantRunner
{
    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function run(TenantId $tenantId, callable $work): mixed;
}
