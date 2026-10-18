<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Catalog\Domain\Sku;
use App\Shared\Domain\DomainRuleViolation;

/**
 * A product that is already blocked was blocked again.
 *
 * Protects CAT-06.
 */
final class ProductAlreadyBlocked extends DomainRuleViolation
{
    public static function forSku(Sku $sku): self
    {
        return new self(
            'catalog.product_already_blocked',
            ['sku' => $sku->value],
            \sprintf('Product %s is already blocked.', $sku->value),
        );
    }
}
