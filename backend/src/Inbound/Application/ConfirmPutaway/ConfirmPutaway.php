<?php

declare(strict_types=1);

namespace App\Inbound\Application\ConfirmPutaway;

use App\Inbound\Domain\PutawayTask;
use App\Shared\Application\Bus\Command;

/**
 * An operator scanned the location where the goods now are. A reason is needed when it is not the
 * suggested one (INB-13).
 *
 * @implements Command<PutawayTask>
 */
final readonly class ConfirmPutaway implements Command
{
    public function __construct(
        public string $taskId,
        public string $locationId,
        public ?string $reason = null,
    ) {
    }
}
