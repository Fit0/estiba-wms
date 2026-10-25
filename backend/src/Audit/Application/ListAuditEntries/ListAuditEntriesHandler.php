<?php

declare(strict_types=1);

namespace App\Audit\Application\ListAuditEntries;

use App\Audit\Domain\AuditEntries;
use App\Audit\Domain\AuditEntry;
use App\Shared\Application\Bus\QueryHandler;
use App\Shared\Application\CurrentTenant;
use App\Shared\Domain\Page;

final readonly class ListAuditEntriesHandler implements QueryHandler
{
    public function __construct(
        private AuditEntries $entries,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @return Page<AuditEntry>
     */
    public function __invoke(ListAuditEntries $query): Page
    {
        return $this->entries->search($this->tenant->id(), $query->filter, $query->page);
    }
}
