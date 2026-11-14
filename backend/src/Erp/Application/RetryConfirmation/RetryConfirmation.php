<?php

declare(strict_types=1);

namespace App\Erp\Application\RetryConfirmation;

use App\Erp\Application\OutboxEntry;
use App\Shared\Application\Bus\Command;

/**
 * A supervisor decides to send again a confirmation that failed, once whatever stopped it is fixed.
 *
 * @implements Command<OutboxEntry>
 */
final readonly class RetryConfirmation implements Command
{
    public function __construct(public string $shipmentId)
    {
    }
}
