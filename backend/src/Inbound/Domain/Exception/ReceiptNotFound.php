<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * No receipt with this identifier exists for the tenant.
 */
final class ReceiptNotFound extends DomainRuleViolation
{
    public static function forId(string $id): self
    {
        return new self(
            'inbound.receipt_not_found',
            ['id' => $id],
            \sprintf('No receipt with id %s.', $id),
        );
    }
}
