<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Something that happened in the domain, named in the past tense.
 */
interface DomainEvent
{
    public function occurredAt(): \DateTimeImmutable;
}
