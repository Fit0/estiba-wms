<?php

declare(strict_types=1);

namespace App\Identity\Domain\Event;

use App\Identity\Domain\Email;
use App\Identity\Domain\UserId;
use App\Shared\Domain\DomainEvent;

final readonly class UserDisabled implements DomainEvent
{
    public function __construct(
        public UserId $userId,
        public Email $email,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
