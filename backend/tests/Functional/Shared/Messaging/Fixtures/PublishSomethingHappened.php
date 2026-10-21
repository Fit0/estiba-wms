<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared\Messaging\Fixtures;

use App\Shared\Application\Bus\Command;

/**
 * A command whose only work is to publish {@see SomethingHappened}.
 *
 * @implements Command<null>
 */
final readonly class PublishSomethingHappened implements Command
{
    public function __construct(
        public string $tenantId,
        public bool $policyFails = false,
    ) {
    }
}
