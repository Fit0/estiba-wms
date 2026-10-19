<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * No product with this identifier exists for the tenant.
 */
final class ProductNotFound extends DomainRuleViolation
{
    public static function forId(string $id): self
    {
        return new self(
            'catalog.product_not_found',
            ['id' => $id],
            \sprintf('No product with id %s.', $id),
        );
    }
}
