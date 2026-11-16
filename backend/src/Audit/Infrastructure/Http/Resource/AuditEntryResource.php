<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Http\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Audit\Application\ListAuditEntries\AuditLine;
use App\Audit\Infrastructure\Http\State\AuditEntryProvider;

/**
 * One line of the audit trail: what happened, when, who did it and what the event carried. The email
 * address of the actor is the one the user has now, `null` when the user is not found or the event has no
 * user (a policy that ran in the worker).
 */
#[ApiResource(
    shortName: 'AuditEntry',
    operations: [
        new GetCollection(uriTemplate: '/audit-log', provider: AuditEntryProvider::class),
    ],
)]
final readonly class AuditEntryResource
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $id,
        public string $eventType,
        public string $occurredAt,
        public string $recordedAt,
        public ?string $actorId,
        public ?string $actorEmail,
        public ?string $subjectId,
        public array $data,
    ) {
    }

    public static function fromLine(AuditLine $line): self
    {
        $entry = $line->entry;

        return new self(
            $entry->id()->value,
            $entry->eventType(),
            $entry->occurredAt()->format(\DATE_ATOM),
            $entry->recordedAt()->format(\DATE_ATOM),
            $entry->actorId(),
            $line->actorEmail,
            $entry->subjectId(),
            $entry->data(),
        );
    }
}
