<?php

declare(strict_types=1);

namespace App\Audit\Application;

use App\Shared\Domain\TenantId;

/**
 * An event told in plain values, ready to be written to the trail.
 */
final readonly class EventDescription
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public string $type,
        public ?string $subjectId,
        public ?TenantId $tenantId,
        public array $data,
    ) {
    }
}
