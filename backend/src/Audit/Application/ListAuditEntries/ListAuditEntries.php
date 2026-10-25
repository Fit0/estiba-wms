<?php

declare(strict_types=1);

namespace App\Audit\Application\ListAuditEntries;

use App\Audit\Domain\AuditEntry;
use App\Audit\Domain\AuditFilter;
use App\Shared\Application\Bus\Query;
use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;

/**
 * The audit trail of the current tenant, the latest first, optionally narrowed.
 *
 * @implements Query<Page<AuditEntry>>
 */
final readonly class ListAuditEntries implements Query
{
    public function __construct(
        public PageRequest $page,
        public AuditFilter $filter = new AuditFilter(),
    ) {
    }
}
