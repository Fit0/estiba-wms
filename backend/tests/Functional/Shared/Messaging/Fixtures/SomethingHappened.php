<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\Messaging\Fixtures;

use App\Shared\Domain\DomainEvent;

/**
 * An event with one policy, {@see SaveTenantWhenSomethingHappened}, which saves a tenant and then,
 * when asked, fails.
 */
final readonly class SomethingHappened implements DomainEvent
{
    public function __construct(
        public string $tenantId,
        public bool $policyFails = false,
    ) {
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-21T10:00:00+00:00');
    }
}
