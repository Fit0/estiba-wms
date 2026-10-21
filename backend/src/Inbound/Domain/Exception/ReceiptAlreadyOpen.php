<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Exception;

use App\Inbound\Domain\AsnReference;
use App\Shared\Domain\DomainRuleViolation;

/**
 * The ASN already has a receipt in progress, so it cannot open another one, be cancelled or be
 * closed until that receipt closes.
 *
 * Protects INB-16.
 */
final class ReceiptAlreadyOpen extends DomainRuleViolation
{
    public static function onAsn(AsnReference $reference): self
    {
        return new self(
            'inbound.asn_receipt_already_open',
            ['reference' => $reference->value],
            \sprintf('ASN %s already has an open receipt.', $reference->value),
        );
    }
}
