<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * Stock of a product the catalog does not know, or that is blocked or discontinued, was asked to
 * be reserved.
 *
 * Protects CAT-06 and STK-06.
 */
final class ProductNotAllocatable extends DomainRuleViolation
{
    public static function unknown(string $productId): self
    {
        return new self(
            'inventory.product_not_allocatable',
            ['productId' => $productId, 'reason' => 'unknown'],
            \sprintf('Product %s does not exist, so no stock of it can be reserved.', $productId),
        );
    }

    public static function unavailable(string $productId): self
    {
        return new self(
            'inventory.product_not_allocatable',
            ['productId' => $productId, 'reason' => 'unavailable'],
            \sprintf('Product %s is blocked or discontinued, so no stock of it can be reserved.', $productId),
        );
    }
}
