<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Erp\Application\ErpImportLog;
use App\Erp\Application\ImportLogEntry;
use App\Erp\Application\ImportResult;
use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;
use App\Shared\Domain\TenantId;

final class InMemoryErpImportLog implements ErpImportLog
{
    /** @var list<ImportLogEntry> */
    public array $entries = [];

    #[\Override]
    public function record(TenantId $tenantId, ImportLogEntry $entry): void
    {
        $this->entries[] = $entry;
    }

    #[\Override]
    public function search(TenantId $tenantId, ?ImportResult $result, PageRequest $page): Page
    {
        $entries = array_values(array_filter($this->entries, static fn (ImportLogEntry $entry): bool => !$result instanceof ImportResult || $entry->result === $result));

        return new Page(\array_slice($entries, $page->offset(), $page->itemsPerPage), \count($entries), $page);
    }
}
