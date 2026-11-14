<?php

declare(strict_types=1);

namespace App\Erp\Application;

use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;
use App\Shared\Domain\TenantId;

/**
 * The files of orders that the import dealt with, so a person can see them without going into the SFTP server (ADR 0050).
 */
interface ErpImportLog
{
    public function record(TenantId $tenantId, ImportLogEntry $entry): void;

    /**
     * The latest first.
     *
     * @return Page<ImportLogEntry>
     */
    public function search(TenantId $tenantId, ?ImportResult $result, PageRequest $page): Page;
}
