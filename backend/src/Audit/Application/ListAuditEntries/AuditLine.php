<?php

declare(strict_types=1);

namespace App\Audit\Application\ListAuditEntries;

use App\Audit\Domain\AuditEntry;

/**
 * One entry of the trail with the name of whoever caused it, as the user is known now. The name is
 * looked up when the trail is read and never written into it (AUD-02), so it follows the user.
 */
final readonly class AuditLine
{
    public function __construct(
        public AuditEntry $entry,
        public ?string $actorEmail,
    ) {
    }
}
