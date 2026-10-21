<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Event;

use App\Inbound\Domain\AsnId;
use App\Shared\Domain\DomainEvent;

/**
 * The announced delivery will not arrive.
 */
final readonly class AsnCancelled implements DomainEvent
{
    public function __construct(
        public AsnId $asnId,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
