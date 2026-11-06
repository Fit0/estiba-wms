<?php

declare(strict_types=1);

namespace App\Erp\Application;

use App\Erp\Domain\Exception\ErpRejectedShipment;
use App\Erp\Domain\Exception\ErpUnavailable;

/**
 * The way the warehouse talks to the ERP. Nothing about how it is done, or about the ERP's own model,
 * shows here (ADR 0047).
 */
interface ErpGateway
{
    /**
     * @throws ErpRejectedShipment the ERP refused it, and sending it again will not change that
     * @throws ErpUnavailable      the ERP could not be reached or is not ready; it can be tried again
     */
    public function confirmShipment(ShipmentConfirmation $confirmation): ConfirmationOutcome;
}
