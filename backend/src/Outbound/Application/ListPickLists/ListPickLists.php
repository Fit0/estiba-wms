<?php

declare(strict_types=1);

namespace App\Outbound\Application\ListPickLists;

use App\Outbound\Domain\PickList;
use App\Shared\Application\Bus\Query;

/**
 * The pick lists of the current tenant, the latest first, optionally those of one wave.
 *
 * @implements Query<list<PickList>>
 */
final readonly class ListPickLists implements Query
{
    public function __construct(public ?string $waveId = null)
    {
    }
}
