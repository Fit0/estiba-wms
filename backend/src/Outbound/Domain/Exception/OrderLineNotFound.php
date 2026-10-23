<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * An allocation names a line the order does not have.
 */
final class OrderLineNotFound extends DomainRuleViolation
{
    public static function number(int $lineNumber): self
    {
        return new self(
            'outbound.order_line_not_found',
            ['line' => $lineNumber],
            \sprintf('The order has no line %d.', $lineNumber),
        );
    }
}
