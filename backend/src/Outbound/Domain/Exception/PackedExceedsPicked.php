<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * More units of a product are in parcels than were picked.
 *
 * Protects OUT-22.
 */
final class PackedExceedsPicked extends DomainRuleViolation
{
    public static function forProduct(string $productId, int $packed, int $picked): self
    {
        return new self('outbound.packed_exceeds_picked', ['productId' => $productId, 'packed' => $packed, 'picked' => $picked], \sprintf('%d units of product %s would be packed and only %d were picked.', $packed, $productId, $picked));
    }
}
