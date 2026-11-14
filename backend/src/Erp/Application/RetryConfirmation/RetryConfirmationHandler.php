<?php

declare(strict_types=1);

namespace App\Erp\Application\RetryConfirmation;

use App\Erp\Application\OutboxEntry;
use App\Erp\Application\ShipmentOutbox;
use App\Erp\Domain\Exception\FailedConfirmationNotFound;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Clock;
use App\Shared\Application\CurrentTenant;

final readonly class RetryConfirmationHandler implements CommandHandler
{
    public function __construct(
        private ShipmentOutbox $outbox,
        private CurrentTenant $tenant,
        private Clock $clock,
    ) {
    }

    public function __invoke(RetryConfirmation $command): OutboxEntry
    {
        return $this->outbox->retry($this->tenant->id(), $command->shipmentId, $this->clock->now())
            ?? throw FailedConfirmationNotFound::forShipment($command->shipmentId);
    }
}
