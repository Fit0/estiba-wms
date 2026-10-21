<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Shared\Application\CurrentUser;
use App\Shared\Application\TenantNotResolved;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * The authenticated user, loaded from the database on every request (ADR 0008).
 */
final readonly class SecurityCurrentUser implements CurrentUser
{
    public function __construct(private Security $security)
    {
    }

    #[\Override]
    public function id(): string
    {
        $user = $this->security->getUser();

        if (!$user instanceof SecurityUser) {
            throw TenantNotResolved::missing();
        }

        return $user->id->value;
    }
}
