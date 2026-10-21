<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Exception;

use App\Inbound\Domain\SupplierName;
use App\Shared\Domain\DomainRuleViolation;

/**
 * The supplier of an ASN is empty, too long or has control characters.
 */
final class InvalidSupplierName extends DomainRuleViolation
{
    public static function forValue(string $value): self
    {
        return new self(
            'inbound.supplier_name_invalid',
            ['maxLength' => SupplierName::MAX_LENGTH],
            \sprintf('The supplier of an ASN must have 1 to %d characters and no control characters, and "%s" was given.', SupplierName::MAX_LENGTH, $value),
        );
    }
}
