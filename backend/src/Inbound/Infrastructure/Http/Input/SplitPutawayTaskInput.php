<?php

declare(strict_types=1);

namespace App\Inbound\Infrastructure\Http\Input;

/**
 * The units that move to a new task.
 */
final readonly class SplitPutawayTaskInput
{
    public function __construct(public int $units)
    {
    }
}
