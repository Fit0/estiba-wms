<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * The approval of an over-receipt is missing, not needed, or already given.
 *
 * Protects INB-08.
 */
final class OverReceiptApproval extends DomainRuleViolation
{
    public static function required(int $units): self
    {
        return new self(
            'inbound.over_receipt_not_approved',
            ['units' => $units],
            \sprintf('The receipt holds %d units more than the ASN announced. A supervisor must approve them before it closes.', $units),
        );
    }

    public static function notNeeded(): self
    {
        return new self(
            'inbound.over_receipt_missing',
            [],
            'The receipt holds no more than the ASN announced, so there is nothing to approve.',
        );
    }

    public static function alreadyGiven(): self
    {
        return new self(
            'inbound.over_receipt_already_approved',
            [],
            'The over-receipt of this receipt is already approved.',
        );
    }
}
