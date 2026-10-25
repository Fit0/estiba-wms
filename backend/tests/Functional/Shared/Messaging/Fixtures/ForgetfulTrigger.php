<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\Messaging\Fixtures;

use App\Shared\Domain\DomainEvent;

/**
 * Something happened that a forgetful policy reacts to. It only exists to see what the guard of the
 * event bus says about a policy that raises events and does not publish them.
 */
final readonly class ForgetfulTrigger implements DomainEvent
{
    public function __construct(public string $tenantId)
    {
    }

    #[\Override]
    public function occurredAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-25T10:00:00+00:00');
    }
}
