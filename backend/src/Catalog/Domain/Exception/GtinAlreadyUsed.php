<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Catalog\Domain\Gtin;
use App\Shared\Domain\DomainRuleViolation;

/**
 * The GTIN already belongs to a product of the tenant, this one or another.
 *
 * Protects CAT-02.
 */
final class GtinAlreadyUsed extends DomainRuleViolation
{
    public static function forGtin(Gtin $gtin): self
    {
        return new self(
            'catalog.gtin_already_used',
            ['gtin' => $gtin->value],
            \sprintf('GTIN %s already belongs to a product of the tenant.', $gtin->value),
        );
    }
}
