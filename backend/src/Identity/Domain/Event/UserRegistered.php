<?php

declare(strict_types=1);

namespace App\Identity\Domain\Event;

use App\Identity\Domain\Email;
use App\Identity\Domain\UserId;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\Sensitive;
use App\Shared\Domain\TenantId;

final readonly class UserRegistered implements DomainEvent
{
    public function __construct(
        public UserId $userId,
        public TenantId $tenantId,
        #[Sensitive]
        public Email $email,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
