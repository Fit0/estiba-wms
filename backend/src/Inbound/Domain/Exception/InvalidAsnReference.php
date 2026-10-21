<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Exception;

use App\Inbound\Domain\AsnReference;
use App\Shared\Domain\DomainRuleViolation;

/**
 * The reference of an ASN is empty, too long or has control characters.
 */
final class InvalidAsnReference extends DomainRuleViolation
{
    public static function forValue(string $value): self
    {
        return new self(
            'inbound.asn_reference_invalid',
            ['maxLength' => AsnReference::MAX_LENGTH],
            \sprintf('The reference of an ASN must have 1 to %d characters and no control characters, and "%s" was given.', AsnReference::MAX_LENGTH, $value),
        );
    }
}
