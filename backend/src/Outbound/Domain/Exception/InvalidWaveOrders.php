<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A wave has no orders, more than it can hold, or the same order twice.
 */
final class InvalidWaveOrders extends DomainRuleViolation
{
    public const int MAX_ORDERS = 200;

    public static function none(): self
    {
        return new self('outbound.wave_orders_invalid', ['reason' => 'empty', 'maxOrders' => self::MAX_ORDERS], 'A wave needs at least one order.');
    }

    public static function tooMany(int $count): self
    {
        return new self(
            'outbound.wave_orders_invalid',
            ['reason' => 'too_many', 'maxOrders' => self::MAX_ORDERS],
            \sprintf('A wave holds %d orders at most, and %d were given.', self::MAX_ORDERS, $count),
        );
    }

    public static function repeated(string $orderId): self
    {
        return new self(
            'outbound.wave_orders_invalid',
            ['reason' => 'repeated', 'maxOrders' => self::MAX_ORDERS],
            \sprintf('Order %s is in the wave twice.', $orderId),
        );
    }
}
