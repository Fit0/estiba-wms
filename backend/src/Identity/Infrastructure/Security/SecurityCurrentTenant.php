<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Shared\Application\CurrentTenant;
use App\Shared\Application\TenantNotResolved;
use App\Shared\Domain\TenantId;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * The tenant of the authenticated user. The tenant is never read from the request itself: a client
 * cannot ask to act for another tenant (ADR 0008).
 */
final readonly class SecurityCurrentTenant implements CurrentTenant
{
    public function __construct(private Security $security)
    {
    }

    #[\Override]
    public function id(): TenantId
    {
        $user = $this->security->getUser();

        if (!$user instanceof SecurityUser) {
            throw TenantNotResolved::missing();
        }

        return $user->tenantId;
    }
}
