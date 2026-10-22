<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * The catalog of the tenant has no product with this identifier, so there is no stock of it to show.
 */
final class ProductNotFound extends DomainRuleViolation
{
    public static function forId(string $id): self
    {
        return new self(
            'inventory.product_not_found',
            ['id' => $id],
            \sprintf('No product with id %s.', $id),
        );
    }
}
