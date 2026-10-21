<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\Messaging\Fixtures;

use App\Shared\Application\Bus\Command;

/**
 * Saves a tenant and publishes {@see SomethingHappened}, whose policy saves another one. Either of
 * them can be asked to fail.
 *
 * @implements Command<null>
 */
final readonly class PublishSomethingHappened implements Command
{
    public function __construct(
        public string $tenantId,
        public string $policyTenantId,
        public bool $policyFails = false,
        public bool $commandFailsAfterPublishing = false,
    ) {
    }
}
