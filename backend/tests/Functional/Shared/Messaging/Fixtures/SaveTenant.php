<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\Messaging\Fixtures;

use App\Shared\Application\Bus\Command;

/**
 * Saves a tenant and then, when asked, fails. It only exists to see what the command bus does
 * with the work of a handler that fails.
 *
 * @implements Command<bool>
 */
final readonly class SaveTenant implements Command
{
    public function __construct(
        public string $tenantId,
        public bool $failAfterFlushing = false,
        public bool $failWithADomainError = false,
    ) {
    }
}
