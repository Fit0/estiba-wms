<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A product cannot be announced or received: the catalog does not have it, it is blocked or
 * discontinued, or no product carries the scanned barcode.
 *
 * Protects INB-01.
 */
final class ProductNotReceivable extends DomainRuleViolation
{
    public static function unknown(string $productId): self
    {
        return new self(
            'inbound.product_unknown',
            ['productId' => $productId],
            \sprintf('The catalog has no product with id %s.', $productId),
        );
    }

    public static function notAvailable(string $productId): self
    {
        return new self(
            'inbound.product_not_available',
            ['productId' => $productId],
            \sprintf('Product %s is blocked or discontinued and cannot be received.', $productId),
        );
    }

    public static function unknownGtin(string $gtin): self
    {
        return new self(
            'inbound.gtin_unknown',
            ['gtin' => $gtin],
            \sprintf('No product of the catalog carries the GTIN %s.', $gtin),
        );
    }
}
