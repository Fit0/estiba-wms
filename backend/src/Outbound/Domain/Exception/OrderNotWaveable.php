<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Outbound\Domain\OrderStatus;
use App\Shared\Domain\DomainRuleViolation;

/**
 * An order that is not allocated, or only partly and without the backorder accepted, was put in a wave.
 *
 * Protects OUT-06.
 */
final class OrderNotWaveable extends DomainRuleViolation
{
    public static function forStatus(OrderStatus $status, bool $backorderAccepted): self
    {
        return new self(
            'outbound.order_not_waveable',
            ['status' => $status->value, 'backorderAccepted' => $backorderAccepted],
            \sprintf('An order that is %s cannot join a wave: it must be allocated, or partially allocated with the backorder accepted.', $status->value),
        );
    }
}
