<?php

declare(strict_types=1);

namespace App\Inbound\Application\ListAsns;

use App\Inbound\Domain\Asn;
use App\Inbound\Domain\AsnRepository;
use App\Shared\Application\Bus\QueryHandler;
use App\Shared\Application\CurrentTenant;
use App\Shared\Domain\Page;

final readonly class ListAsnsHandler implements QueryHandler
{
    public function __construct(
        private AsnRepository $asns,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @return Page<Asn>
     */
    public function __invoke(ListAsns $query): Page
    {
        return $this->asns->search($this->tenant->id(), $query->status, $query->page);
    }
}
