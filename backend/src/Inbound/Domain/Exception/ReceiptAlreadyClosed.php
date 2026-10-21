<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Exception;

use App\Inbound\Domain\ReceiptId;
use App\Shared\Domain\DomainRuleViolation;

/**
 * A closed receipt was asked to change.
 *
 * Protects INB-09.
 */
final class ReceiptAlreadyClosed extends DomainRuleViolation
{
    public static function forId(ReceiptId $id): self
    {
        return new self(
            'inbound.receipt_already_closed',
            ['id' => $id->value],
            \sprintf('Receipt %s is closed and cannot change.', $id->value),
        );
    }
}
