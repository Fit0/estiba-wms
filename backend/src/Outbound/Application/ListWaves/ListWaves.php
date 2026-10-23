<?php

declare(strict_types=1);

namespace App\Outbound\Application\ListWaves;

use App\Outbound\Domain\Wave;
use App\Outbound\Domain\WaveStatus;
use App\Shared\Application\Bus\Query;
use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;

/**
 * The waves of the current tenant, optionally narrowed to one status.
 *
 * @implements Query<Page<Wave>>
 */
final readonly class ListWaves implements Query
{
    public function __construct(
        public PageRequest $page,
        public ?WaveStatus $status = null,
    ) {
    }
}
