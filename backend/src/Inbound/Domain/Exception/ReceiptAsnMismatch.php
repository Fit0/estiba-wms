<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Exception;

use App\Inbound\Domain\AsnId;
use App\Inbound\Domain\ReceiptId;
use App\Shared\Domain\DomainRuleViolation;

/**
 * A receipt was closed through an ASN that is not its own, or that does not have it open.
 *
 * Protects INB-05.
 */
final class ReceiptAsnMismatch extends DomainRuleViolation
{
    public static function between(ReceiptId $receiptId, AsnId $asnId): self
    {
        return new self(
            'inbound.receipt_asn_mismatch',
            ['receiptId' => $receiptId->value, 'asnId' => $asnId->value],
            \sprintf('Receipt %s is not the open receipt of ASN %s.', $receiptId->value, $asnId->value),
        );
    }
}
