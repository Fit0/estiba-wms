<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Email;
use App\Identity\Domain\Role;
use App\Identity\Domain\User;
use App\Identity\Domain\UserId;
use App\Shared\Domain\TenantId;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The user as Symfony's security layer sees it. It is built from the domain {@see User} on every
 * request, so a change of roles or a disabled account takes effect at once.
 */
final readonly class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * @param non-empty-string                 $passwordHash
     * @param non-empty-list<non-empty-string> $roles        Symfony role names, such as `ROLE_ADMIN`
     */
    public function __construct(
        public UserId $id,
        public TenantId $tenantId,
        public Email $email,
        #[\SensitiveParameter]
        private string $passwordHash,
        private array $roles,
        public bool $active,
    ) {
    }

    public static function fromDomain(User $user): self
    {
        return new self(
            $user->id(),
            $user->tenantId(),
            $user->email(),
            $user->passwordHash()->value,
            array_map(static fn (Role $role): string => 'ROLE_'.strtoupper($role->value), $user->roles()),
            $user->isActive(),
        );
    }

    #[\Override]
    public function getRoles(): array
    {
        return $this->roles;
    }

    #[\Override]
    public function getPassword(): string
    {
        return $this->passwordHash;
    }

    /**
     * @return non-empty-string
     */
    #[\Override]
    public function getUserIdentifier(): string
    {
        return $this->email->value;
    }

    #[\Override]
    public function eraseCredentials(): void
    {
        // Nothing to erase: the hash is a private read-only property and the plain password is never kept.
    }
}
