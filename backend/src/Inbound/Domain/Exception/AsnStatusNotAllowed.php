<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Exception;

use App\Inbound\Domain\AsnReference;
use App\Inbound\Domain\AsnStatus;
use App\Shared\Domain\DomainRuleViolation;

/**
 * The ASN is not in a status that allows the change: only an ASN that is still expected can be
 * cancelled, and only one that has received something can be closed.
 */
final class AsnStatusNotAllowed extends DomainRuleViolation
{
    public static function toCancel(AsnReference $reference, AsnStatus $status): self
    {
        return new self(
            'inbound.asn_not_cancellable',
            ['reference' => $reference->value, 'status' => $status->value],
            \sprintf('ASN %s is %s. Only an expected ASN can be cancelled.', $reference->value, $status->value),
        );
    }

    public static function toClose(AsnReference $reference, AsnStatus $status): self
    {
        return new self(
            'inbound.asn_not_closable',
            ['reference' => $reference->value, 'status' => $status->value],
            \sprintf('ASN %s is %s. Only an ASN that has received goods can be closed.', $reference->value, $status->value),
        );
    }
}
