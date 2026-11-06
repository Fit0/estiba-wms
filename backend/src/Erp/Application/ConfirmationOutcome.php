<?php

declare(strict_types=1);

namespace App\Erp\Application;

/**
 * What the ERP did with a confirmation of shipment. Both mean the ERP knows the order shipped: a
 * confirmation sent again after a lost answer lands on the second.
 */
enum ConfirmationOutcome
{
    case Confirmed;
    case AlreadyConfirmed;
}
