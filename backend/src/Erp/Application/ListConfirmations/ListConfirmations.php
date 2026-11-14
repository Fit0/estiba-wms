<?php

declare(strict_types=1);

namespace App\Erp\Application\ListConfirmations;

use App\Erp\Application\OutboxEntry;
use App\Erp\Application\OutboxStatus;
use App\Shared\Application\Bus\Query;
use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;

/**
 * The confirmations of shipments to the ERP, the latest first, optionally in one state.
 *
 * @implements Query<Page<OutboxEntry>>
 */
final readonly class ListConfirmations implements Query
{
    public function __construct(
        public PageRequest $page,
        public ?OutboxStatus $status = null,
    ) {
    }
}
