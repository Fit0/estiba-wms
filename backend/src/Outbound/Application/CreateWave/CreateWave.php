<?php

declare(strict_types=1);

namespace App\Outbound\Application\CreateWave;

use App\Outbound\Domain\Wave;
use App\Shared\Application\Bus\Command;

/**
 * Groups orders into a wave. Each one must be allocated, or partly allocated with its backorder accepted, and in no other wave.
 *
 * @implements Command<Wave>
 */
final readonly class CreateWave implements Command
{
    /**
     * @param list<string> $orderIds
     */
    public function __construct(public array $orderIds)
    {
    }
}
