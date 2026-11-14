<?php

declare(strict_types=1);

namespace App\Erp\Application\ListConfirmations;

use App\Erp\Application\OutboxEntry;
use App\Erp\Application\ShipmentOutbox;
use App\Shared\Application\Bus\QueryHandler;
use App\Shared\Application\CurrentTenant;
use App\Shared\Domain\Page;

final readonly class ListConfirmationsHandler implements QueryHandler
{
    public function __construct(
        private ShipmentOutbox $outbox,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @return Page<OutboxEntry>
     */
    public function __invoke(ListConfirmations $query): Page
    {
        return $this->outbox->search($this->tenant->id(), $query->status, $query->page);
    }
}
