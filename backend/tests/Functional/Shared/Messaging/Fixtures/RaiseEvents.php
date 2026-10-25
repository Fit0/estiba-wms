<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\Messaging\Fixtures;

use App\Shared\Application\Bus\Command;

/**
 * Registers a tenant, which raises an event, and then publishes it or forgets it. It only exists to see
 * what the guard of the command bus says about a handler that forgets.
 *
 * @implements Command<bool>
 */
final readonly class RaiseEvents implements Command
{
    public function __construct(
        public string $tenantId,
        public bool $publish,
    ) {
    }
}
