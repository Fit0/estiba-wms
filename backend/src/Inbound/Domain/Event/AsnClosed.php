<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Event;

use App\Inbound\Domain\AsnId;
use App\Inbound\Domain\AsnStatus;
use App\Shared\Domain\DomainEvent;

/**
 * Nothing more will be received against the ASN. Closing from partially received means the
 * delivery ended with a shortage.
 */
final readonly class AsnClosed implements DomainEvent
{
    public function __construct(
        public AsnId $asnId,
        public AsnStatus $closedFrom,
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
