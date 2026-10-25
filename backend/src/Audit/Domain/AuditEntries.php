<?php

declare(strict_types=1);

namespace App\Audit\Domain;

use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;
use App\Shared\Domain\TenantId;

/**
 * The trail only grows: there is no way to change or remove an entry (AUD-01).
 */
interface AuditEntries
{
    public function add(AuditEntry $entry): void;

    /**
     * The tenant's entries, the latest first.
     *
     * @return Page<AuditEntry>
     */
    public function search(TenantId $tenantId, AuditFilter $filter, PageRequest $page): Page;
}
