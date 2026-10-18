<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Catalog\Domain\Sku;
use App\Shared\Domain\DomainRuleViolation;

/**
 * A change was attempted on a discontinued product, which is final.
 *
 * Protects CAT-08.
 */
final class ProductDiscontinued extends DomainRuleViolation
{
    public static function forSku(Sku $sku): self
    {
        return new self(
            'catalog.product_discontinued',
            ['sku' => $sku->value],
            \sprintf('Product %s is discontinued and cannot change.', $sku->value),
        );
    }
}
