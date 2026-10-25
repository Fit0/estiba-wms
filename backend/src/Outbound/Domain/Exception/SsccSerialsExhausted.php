<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * Every serial that the GS1 company prefix leaves room for has been used.
 */
final class SsccSerialsExhausted extends DomainRuleViolation
{
    public static function forPrefix(string $companyPrefix): self
    {
        return new self('outbound.sscc_serials_exhausted', ['prefix' => $companyPrefix], \sprintf('No SSCC serial is left for the company prefix %s.', $companyPrefix));
    }
}
