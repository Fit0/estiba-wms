<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Exception;

use App\Inbound\Domain\AsnReference;
use App\Inbound\Domain\AsnStatus;
use App\Shared\Domain\DomainRuleViolation;

/**
 * A receipt was opened against an ASN that is closed or cancelled.
 *
 * Protects INB-04.
 */
final class AsnNotReceivable extends DomainRuleViolation
{
    public static function inStatus(AsnReference $reference, AsnStatus $status): self
    {
        return new self(
            'inbound.asn_not_receivable',
            ['reference' => $reference->value, 'status' => $status->value],
            \sprintf('ASN %s is %s, so nothing can be received against it.', $reference->value, $status->value),
        );
    }
}
