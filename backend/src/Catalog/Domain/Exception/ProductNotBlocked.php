<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Catalog\Domain\Sku;
use App\Shared\Domain\DomainRuleViolation;

/**
 * A product that is not blocked was unblocked.
 *
 * Protects CAT-06.
 */
final class ProductNotBlocked extends DomainRuleViolation
{
    public static function forSku(Sku $sku): self
    {
        return new self(
            'catalog.product_not_blocked',
            ['sku' => $sku->value],
            \sprintf('Product %s is not blocked.', $sku->value),
        );
    }
}
