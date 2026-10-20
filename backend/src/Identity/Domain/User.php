<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use App\Identity\Domain\Event\UserDisabled;
use App\Identity\Domain\Event\UserEnabled;
use App\Identity\Domain\Event\UserRegistered;
use App\Identity\Domain\Exception\EmailAlreadyUsed;
use App\Identity\Domain\Exception\UserAlreadyDisabled;
use App\Identity\Domain\Exception\UserNotDisabled;
use App\Identity\Domain\Exception\UserWithoutRole;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\TenantId;

/**
 * A person who signs in. It belongs to one tenant for its whole life (IDN-05), and what it may
 * do depends on its roles.
 */
final class User extends AggregateRoot
{
    private UserStatus $status = UserStatus::Active;

    /**
     * @param non-empty-list<Role> $roles
     */
    private function __construct(
        private readonly UserId $id,
        private readonly TenantId $tenantId,
        private readonly Email $email,
        private readonly PasswordHash $passwordHash,
        private readonly array $roles,
    ) {
    }

    /**
     * @param list<Role> $roles
     *
     * @throws EmailAlreadyUsed IDN-01
     * @throws UserWithoutRole  IDN-04
     */
    public static function register(
        UserId $id,
        TenantId $tenantId,
        Email $email,
        PasswordHash $passwordHash,
        array $roles,
        EmailRegistry $registry,
        \DateTimeImmutable $at,
    ): self {
        $unique = array_values(array_unique($roles, \SORT_REGULAR));
        if ([] === $unique) {
            throw UserWithoutRole::none();
        }

        if ($registry->isTaken($email)) {
            throw EmailAlreadyUsed::forEmail($email);
        }

        $user = new self($id, $tenantId, $email, $passwordHash, $unique);
        $user->record(new UserRegistered($id, $tenantId, $email, $at));

        return $user;
    }

    /**
     * @throws UserAlreadyDisabled
     */
    public function disable(\DateTimeImmutable $at): void
    {
        if (UserStatus::Disabled === $this->status) {
            throw UserAlreadyDisabled::forEmail($this->email);
        }

        $this->status = UserStatus::Disabled;
        $this->record(new UserDisabled($this->id, $this->email, $at));
    }

    /**
     * @throws UserNotDisabled
     */
    public function enable(\DateTimeImmutable $at): void
    {
        if (UserStatus::Disabled !== $this->status) {
            throw UserNotDisabled::forEmail($this->email);
        }

        $this->status = UserStatus::Active;
        $this->record(new UserEnabled($this->id, $this->email, $at));
    }

    /**
     * Whether this user may sign in and use a token it already holds (IDN-06).
     */
    public function isActive(): bool
    {
        return UserStatus::Active === $this->status;
    }

    public function hasRole(Role $role): bool
    {
        return \in_array($role, $this->roles, true);
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function passwordHash(): PasswordHash
    {
        return $this->passwordHash;
    }

    /**
     * @return non-empty-list<Role>
     */
    public function roles(): array
    {
        return $this->roles;
    }

    public function status(): UserStatus
    {
        return $this->status;
    }
}
