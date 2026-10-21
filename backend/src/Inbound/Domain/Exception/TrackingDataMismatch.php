<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Exception;

use App\Inbound\Domain\ProductId;
use App\Shared\Domain\DomainRuleViolation;

/**
 * The lot or the expiry date of a received item does not match how the product is tracked:
 * missing when the product asks for it, or given when it does not.
 *
 * Protects INB-07 (and, for the stock it becomes, STK-07).
 */
final class TrackingDataMismatch extends DomainRuleViolation
{
    public static function lotRequired(ProductId $productId): self
    {
        return new self(
            'inbound.lot_required',
            ['productId' => $productId->value],
            \sprintf('Product %s is tracked by lot, so a lot number is required.', $productId->value),
        );
    }

    public static function lotNotTracked(ProductId $productId): self
    {
        return new self(
            'inbound.lot_not_tracked',
            ['productId' => $productId->value],
            \sprintf('Product %s is not tracked by lot, so it cannot be received with one.', $productId->value),
        );
    }

    public static function expiryDateRequired(ProductId $productId): self
    {
        return new self(
            'inbound.expiry_date_required',
            ['productId' => $productId->value],
            \sprintf('Product %s is tracked by expiry date, so an expiry date is required.', $productId->value),
        );
    }

    public static function expiryDateNotTracked(ProductId $productId): self
    {
        return new self(
            'inbound.expiry_date_not_tracked',
            ['productId' => $productId->value],
            \sprintf('Product %s is not tracked by expiry date, so it cannot be received with one.', $productId->value),
        );
    }
}
