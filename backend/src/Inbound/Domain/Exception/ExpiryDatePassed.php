<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * Goods arrived with an expiry date that has already passed.
 *
 * Protects INB-07.
 */
final class ExpiryDatePassed extends DomainRuleViolation
{
    public static function on(\DateTimeImmutable $expiryDate): self
    {
        return new self(
            'inbound.expiry_date_passed',
            ['expiryDate' => $expiryDate->format('Y-m-d')],
            \sprintf('The goods expired on %s and cannot be received.', $expiryDate->format('Y-m-d')),
        );
    }
}
