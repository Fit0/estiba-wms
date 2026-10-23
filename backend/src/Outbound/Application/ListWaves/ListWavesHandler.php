<?php

declare(strict_types=1);

namespace App\Outbound\Application\ListWaves;

use App\Outbound\Domain\Wave;
use App\Outbound\Domain\WaveRepository;
use App\Shared\Application\Bus\QueryHandler;
use App\Shared\Application\CurrentTenant;
use App\Shared\Domain\Page;

final readonly class ListWavesHandler implements QueryHandler
{
    public function __construct(
        private WaveRepository $waves,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @return Page<Wave>
     */
    public function __invoke(ListWaves $query): Page
    {
        return $this->waves->search($this->tenant->id(), $query->status, $query->page);
    }
}
