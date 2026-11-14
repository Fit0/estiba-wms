<?php

declare(strict_types=1);

namespace App\Erp\Application\ListImports;

use App\Erp\Application\ImportLogEntry;
use App\Erp\Application\ImportResult;
use App\Shared\Application\Bus\Query;
use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;

/**
 * The files of orders that the import dealt with, the latest first, optionally only the processed or the rejected.
 *
 * @implements Query<Page<ImportLogEntry>>
 */
final readonly class ListImports implements Query
{
    public function __construct(
        public PageRequest $page,
        public ?ImportResult $result = null,
    ) {
    }
}
