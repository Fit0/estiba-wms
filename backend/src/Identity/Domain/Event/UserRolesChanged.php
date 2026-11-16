<?php

declare(strict_types=1);

namespace App\Identity\Domain\Event;

use App\Identity\Domain\Role;
use App\Identity\Domain\UserId;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\TenantId;

final readonly class UserRolesChanged implements DomainEvent
{
    /**
     * @param non-empty-list<Role> $roles what the user may do from now on
     */
    public function __construct(
        public UserId $userId,
        public TenantId $tenantId,
        public array $roles,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    #[\Override]
    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
