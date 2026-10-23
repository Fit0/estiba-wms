<?php

declare(strict_types=1);

namespace App\Outbound\Domain;

/**
 * How soon the order should leave. Waves use it to decide what goes first.
 */
enum OrderPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';
}
