<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Email;
use App\Identity\Domain\Exception\InvalidEmail;
use App\Identity\Domain\User;
use App\Identity\Domain\UserRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Loads the user from the database on every request, also for a valid token. That is what makes
 * a disabled user, or a change of roles, take effect before the token expires (ADR 0008).
 *
 * @implements UserProviderInterface<SecurityUser>
 */
final readonly class DoctrineUserProvider implements UserProviderInterface
{
    public function __construct(private UserRepository $users)
    {
    }

    #[\Override]
    public function loadUserByIdentifier(string $identifier): SecurityUser
    {
        try {
            $user = $this->users->findByEmail(Email::fromString($identifier));
        } catch (InvalidEmail $error) {
            throw new UserNotFoundException(message: $error->getMessage(), code: $error->getCode(), previous: $error);
        }

        if (!$user instanceof User) {
            $notFound = new UserNotFoundException();
            $notFound->setUserIdentifier($identifier);

            throw $notFound;
        }

        return SecurityUser::fromDomain($user);
    }

    #[\Override]
    public function refreshUser(UserInterface $user): SecurityUser
    {
        if (!$user instanceof SecurityUser) {
            throw new UnsupportedUserException(\sprintf('Unsupported user class "%s".', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    #[\Override]
    public function supportsClass(string $class): bool
    {
        return SecurityUser::class === $class || is_subclass_of($class, SecurityUser::class);
    }
}
