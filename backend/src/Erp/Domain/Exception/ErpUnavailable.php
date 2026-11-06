<?php

declare(strict_types=1);

namespace App\Erp\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * The ERP did not answer, or answered that it is not ready for the request yet. Trying again later can
 * work.
 */
final class ErpUnavailable extends DomainRuleViolation
{
    public static function because(string $reason): self
    {
        return new self(
            'erp.unavailable',
            ['reason' => $reason],
            \sprintf('The ERP is not available: %s', $reason),
        );
    }
}
