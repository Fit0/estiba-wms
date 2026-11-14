<?php

declare(strict_types=1);

namespace App\Erp\Application\ListImports;

use App\Erp\Application\ErpImportLog;
use App\Erp\Application\ImportLogEntry;
use App\Shared\Application\Bus\QueryHandler;
use App\Shared\Application\CurrentTenant;
use App\Shared\Domain\Page;

final readonly class ListImportsHandler implements QueryHandler
{
    public function __construct(
        private ErpImportLog $log,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @return Page<ImportLogEntry>
     */
    public function __invoke(ListImports $query): Page
    {
        return $this->log->search($this->tenant->id(), $query->result, $query->page);
    }
}
