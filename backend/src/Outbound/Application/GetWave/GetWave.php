<?php

declare(strict_types=1);

namespace App\Outbound\Application\GetWave;

use App\Outbound\Domain\Wave;
use App\Shared\Application\Bus\Query;

/**
 * One wave of the current tenant.
 *
 * @implements Query<Wave>
 */
final readonly class GetWave implements Query
{
    public function __construct(public string $waveId)
    {
    }
}
