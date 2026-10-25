<?php

declare(strict_types=1);

namespace App\Audit\Domain;

/**
 * What to look for in the trail. Every part is optional and they narrow each other.
 */
final readonly class AuditFilter
{
    public function __construct(
        public ?string $eventType = null,
        public ?string $actorId = null,
        public ?string $subjectId = null,
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $until = null,
    ) {
    }
}
