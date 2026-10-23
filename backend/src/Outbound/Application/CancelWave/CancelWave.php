<?php

declare(strict_types=1);

namespace App\Outbound\Application\CancelWave;

use App\Outbound\Domain\Wave;
use App\Shared\Application\Bus\Command;

/**
 * Calls a wave off, planned or on the floor. Its orders are free again and its pick lists are cancelled.
 *
 * @implements Command<Wave>
 */
final readonly class CancelWave implements Command
{
    public function __construct(public string $waveId)
    {
    }
}
