<?php

declare(strict_types=1);

namespace App\Audit\Infrastructure\Http\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Audit\Domain\AuditEntry;
use App\Audit\Infrastructure\Http\State\AuditEntryProvider;

/**
 * One line of the audit trail: what happened, when, who did it and what the event carried.
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
        public ?string $subjectId,
        public array $data,
    ) {
    }

    public static function fromDomain(AuditEntry $entry): self
    {
        return new self(
            $entry->id()->value,
            $entry->eventType(),
            $entry->occurredAt()->format(\DATE_ATOM),
            $entry->recordedAt()->format(\DATE_ATOM),
            $entry->actorId(),
            $entry->subjectId(),
            $entry->data(),
        );
    }
}
