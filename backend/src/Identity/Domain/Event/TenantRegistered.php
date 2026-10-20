<?php

declare(strict_types=1);

namespace App\Identity\Domain\Event;

use App\Identity\Domain\TenantName;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\Language;
use App\Shared\Domain\TenantId;

final readonly class TenantRegistered implements DomainEvent
{
    public function __construct(
        public TenantId $tenantId,
        public TenantName $name,
        public Language $defaultLanguage,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
