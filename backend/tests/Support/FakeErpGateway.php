<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Erp\Application\ConfirmationOutcome;
use App\Erp\Application\ErpGateway;
use App\Erp\Application\ShipmentConfirmation;

/**
 * An ERP that answers what the test says, and remembers what it was told.
 */
final class FakeErpGateway implements ErpGateway
{
    /** @var list<ShipmentConfirmation> */
    public array $received = [];

    /** @var list<\Throwable|ConfirmationOutcome> what to do with the next calls, in order; once it runs out, the ERP confirms */
    public array $script = [];

    #[\Override]
    public function confirmShipment(ShipmentConfirmation $confirmation): ConfirmationOutcome
    {
        $this->received[] = $confirmation;
        $next = array_shift($this->script) ?? ConfirmationOutcome::Confirmed;
        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }
}
