<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Exception;

use App\Inbound\Domain\AsnReference;
use App\Shared\Domain\DomainRuleViolation;

/**
 * The tenant already has an ASN with this reference, so registering it again would count the same
 * delivery twice.
 *
 * Protects INB-03.
 */
final class AsnReferenceAlreadyUsed extends DomainRuleViolation
{
    public static function forReference(AsnReference $reference): self
    {
        return new self(
            'inbound.asn_reference_already_used',
            ['reference' => $reference->value],
            \sprintf('The tenant already has an ASN with reference %s.', $reference->value),
        );
    }
}
