<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * No ASN with this identifier exists for the tenant.
 */
final class AsnNotFound extends DomainRuleViolation
{
    public static function forId(string $id): self
    {
        return new self(
            'inbound.asn_not_found',
            ['id' => $id],
            \sprintf('No ASN with id %s.', $id),
        );
    }
}
