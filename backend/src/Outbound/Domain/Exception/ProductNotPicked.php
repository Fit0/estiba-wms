<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A parcel holds a product that was not picked for the order.
 *
 * Protects OUT-22.
 */
final class ProductNotPicked extends DomainRuleViolation
{
    public static function forProduct(string $productId): self
    {
        return new self('outbound.product_not_picked', ['productId' => $productId], \sprintf('Product %s was not picked for this order.', $productId));
    }
}
