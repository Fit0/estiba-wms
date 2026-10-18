<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Catalog\Domain\Sku;
use App\Shared\Domain\DomainRuleViolation;

/**
 * The tenant already has a product with this SKU.
 *
 * Protects CAT-01.
 */
final class SkuAlreadyUsed extends DomainRuleViolation
{
    public static function forSku(Sku $sku): self
    {
        return new self(
            'catalog.sku_already_used',
            ['sku' => $sku->value],
            \sprintf('The tenant already has a product with SKU %s.', $sku->value),
        );
    }
}
