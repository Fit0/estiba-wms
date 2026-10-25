<?php

declare(strict_types=1);

namespace App\Audit\Application;

use App\Audit\Domain\AuditEntries;
use App\Audit\Domain\AuditEntry;
use App\Audit\Domain\AuditEntryId;
use App\Shared\Application\Clock;
use App\Shared\Application\CurrentTenant;
use App\Shared\Application\CurrentUser;
use App\Shared\Application\IdGenerator;
use App\Shared\Application\TenantNotResolved;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\TenantId;
use Psr\Log\LoggerInterface;

/**
 * Writes down every domain event that is published (AUD-02). It runs where the event is published,
 * inside the transaction of the change, so a change that is rolled back leaves no trace and one
 * that is committed always does.
 *
 * The tenant is the one the event says, since a policy may run in a worker where nobody is signed
 * in, or else the one of the signed-in user. The actor is the signed-in user, if any.
 */
final readonly class AuditTrail
{
    public function __construct(
        private EventDescriber $describer,
        private AuditEntries $entries,
        private CurrentTenant $tenant,
        private CurrentUser $user,
        private IdGenerator $ids,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function record(DomainEvent $event): void
    {
        $description = $this->describer->describe($event);
        $tenantId = $description->tenantId ?? $this->signedInTenant();

        if (!$tenantId instanceof TenantId) {
            // Failing here would undo a change that is otherwise valid, so it is reported instead.
            $this->logger->warning('A domain event has no tenant and nobody is signed in, so it is not in the audit trail.', ['event' => $description->type]);

            return;
        }

        $this->entries->add(new AuditEntry(
            AuditEntryId::fromString($this->ids->next()),
            $tenantId,
            $description->type,
            $event->occurredAt(),
            $this->clock->now(),
            $this->signedInUser(),
            $description->subjectId,
            $description->data,
        ));
    }

    private function signedInTenant(): ?TenantId
    {
        try {
            return $this->tenant->id();
        } catch (TenantNotResolved) {
            return null;
        }
    }

    private function signedInUser(): ?string
    {
        try {
            return $this->user->id();
        } catch (TenantNotResolved) {
            return null;
        }
    }
}
