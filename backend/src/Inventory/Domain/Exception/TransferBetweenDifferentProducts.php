<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Inventory\Domain\ProductId;
use App\Shared\Domain\DomainRuleViolation;

/**
 * A transfer moves units from a stock item of one product to a stock item of another.
 *
 * Protects STK-08.
 */
final class TransferBetweenDifferentProducts extends DomainRuleViolation
{
    public static function between(ProductId $source, ProductId $destination): self
    {
        return new self(
            'inventory.transfer_product_mismatch',
            ['sourceProductId' => $source->value, 'destinationProductId' => $destination->value],
            'Units can only be transferred between stock items of the same product.',
        );
    }
}
