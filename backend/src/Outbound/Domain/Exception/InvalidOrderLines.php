<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * An order has no lines, or more than it can hold.
 *
 * Protects OUT-02.
 */
final class InvalidOrderLines extends DomainRuleViolation
{
    public const int MAX_LINES = 500;

    public static function none(): self
    {
        return new self('outbound.order_lines_invalid', ['reason' => 'empty', 'maxLines' => self::MAX_LINES], 'An order needs at least one line.');
    }

    public static function tooMany(int $count): self
    {
        return new self(
            'outbound.order_lines_invalid',
            ['reason' => 'too_many', 'maxLines' => self::MAX_LINES],
            \sprintf('An order holds %d lines at most, and %d were given.', self::MAX_LINES, $count),
        );
    }
}
