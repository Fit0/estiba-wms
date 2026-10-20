<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A disabled user cannot sign in, and a token it already holds stops working at once (IDN-06).
 * The failure is the same as a wrong password, so it does not tell a stranger the account exists.
 */
final readonly class UserChecker implements UserCheckerInterface
{
    #[\Override]
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof SecurityUser && !$user->active) {
            throw new DisabledException();
        }
    }

    #[\Override]
    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
