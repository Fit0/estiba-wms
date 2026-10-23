<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A line asks for a product the catalog does not know, or one that is blocked or discontinued.
 *
 * Protects OUT-02.
 */
final class ProductNotOrderable extends DomainRuleViolation
{
    public static function unknown(string $productId): self
    {
        return new self(
            'outbound.product_not_orderable',
            ['productId' => $productId, 'reason' => 'unknown'],
            \sprintf('Product %s does not exist, so it cannot be ordered.', $productId),
        );
    }

    public static function unavailable(string $productId): self
    {
        return new self(
            'outbound.product_not_orderable',
            ['productId' => $productId, 'reason' => 'unavailable'],
            \sprintf('Product %s is blocked or discontinued, so it cannot be ordered.', $productId),
        );
    }
}
