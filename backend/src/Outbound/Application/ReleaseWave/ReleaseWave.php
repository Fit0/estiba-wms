<?php

declare(strict_types=1);

namespace App\Outbound\Application\ReleaseWave;

use App\Outbound\Domain\Wave;
use App\Shared\Application\Bus\Command;

/**
 * Sends a wave to the floor, split into this many pick lists, one for each operator who will walk it.
 *
 * @implements Command<Wave>
 */
final readonly class ReleaseWave implements Command
{
    public function __construct(
        public string $waveId,
        public int $pickLists = 1,
    ) {
    }
}
