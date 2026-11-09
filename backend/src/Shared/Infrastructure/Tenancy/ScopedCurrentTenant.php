<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Tenancy;

use App\Shared\Application\CurrentTenant;
use App\Shared\Application\TenantRunner;
use App\Shared\Domain\TenantId;

/**
 * The tenant a job runs for, if one is set with {@see run()}; otherwise the one of the signed-in user
 * (ADR 0048). A request never sets one, so a client still cannot choose its tenant (ADR 0008).
 */
final class ScopedCurrentTenant implements CurrentTenant, TenantRunner
{
    private ?TenantId $scoped = null;

    public function __construct(private readonly CurrentTenant $ofTheUser)
    {
    }

    #[\Override]
    public function id(): TenantId
    {
        return $this->scoped ?? $this->ofTheUser->id();
    }

    #[\Override]
    public function run(TenantId $tenantId, callable $work): mixed
    {
        $previous = $this->scoped;
        $this->scoped = $tenantId;

        try {
            return $work();
        } finally {
            $this->scoped = $previous;
        }
    }
}
