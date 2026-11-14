<?php

declare(strict_types=1);

namespace App\Erp\Application;

/**
 * Where a confirmation of a shipment is on its way to the ERP (ADR 0049).
 */
enum OutboxStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
}
