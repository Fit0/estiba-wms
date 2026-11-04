<?php

declare(strict_types=1);

namespace App\Analytics\Domain\Exception;

use App\Analytics\Domain\KpiPeriod;
use App\Shared\Domain\DomainRuleViolation;

/**
 * A period of figures that ends before it starts, or lasts more than a year.
 */
final class InvalidKpiPeriod extends DomainRuleViolation
{
    public static function notForward(): self
    {
        return new self('analytics.period_invalid', ['reason' => 'not_forward', 'maxDays' => KpiPeriod::MAX_DAYS], 'A period must end after it starts.');
    }

    public static function tooLong(): self
    {
        return new self('analytics.period_invalid', ['reason' => 'too_long', 'maxDays' => KpiPeriod::MAX_DAYS], \sprintf('A period lasts %d days at most.', KpiPeriod::MAX_DAYS));
    }
}
