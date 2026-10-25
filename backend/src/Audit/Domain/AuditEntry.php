<?php

declare(strict_types=1);

namespace App\Audit\Domain;

use App\Shared\Domain\TenantId;

/**
 * What happened in the domain, who did it and when: one line of the audit trail. It is written once,
 * when the domain event is published, and it is never changed or deleted (AUD-01).
 *
 * It is a record, not an aggregate: it has no behavior and nothing depends on it, so it has no
 * events of its own.
 */
final readonly class AuditEntry
{
    /**
     * @param array<string, mixed> $data what the event carried, as plain values
     */
    public function __construct(
        private AuditEntryId $id,
        private TenantId $tenantId,
        private string $eventType,
        private \DateTimeImmutable $occurredAt,
        private \DateTimeImmutable $recordedAt,
        private ?string $actorId,
        private ?string $subjectId,
        private array $data,
    ) {
    }

    public function id(): AuditEntryId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    /**
     * A stable name such as `outbound.pick_list_started`: the context and the event.
     */
    public function eventType(): string
    {
        return $this->eventType;
    }

    /**
     * When the event happened, as the event says.
     */
    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * When the trail wrote it down.
     */
    public function recordedAt(): \DateTimeImmutable
    {
        return $this->recordedAt;
    }

    /**
     * The user who acted, or null when nobody was signed in: a worker handling a policy.
     */
    public function actorId(): ?string
    {
        return $this->actorId;
    }

    /**
     * The identifier of the aggregate the event is about, such as the pick list that started.
     */
    public function subjectId(): ?string
    {
        return $this->subjectId;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }
}
