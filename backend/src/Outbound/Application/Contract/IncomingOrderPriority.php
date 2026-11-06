<?php

declare(strict_types=1);

namespace App\Outbound\Application\Contract;

/**
 * How soon an order that comes from outside should leave, in the words of the contract. Outbound
 * turns it into its own priority.
 */
enum IncomingOrderPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';
}
