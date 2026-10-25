<?php

declare(strict_types=1);

namespace App\Audit\Domain;

use App\Shared\Domain\Identifier;

/**
 * One line of the audit trail.
 */
final readonly class AuditEntryId extends Identifier
{
}
