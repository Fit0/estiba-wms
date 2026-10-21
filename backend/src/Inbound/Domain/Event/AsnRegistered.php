<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Event;

use App\Inbound\Domain\AsnId;
use App\Inbound\Domain\AsnReference;
use App\Shared\Domain\DomainEvent;

/**
 * A supplier announced a delivery.
 */
final readonly class AsnRegistered implements DomainEvent
{
    public function __construct(
        public AsnId $asnId,
        public AsnReference $reference,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
