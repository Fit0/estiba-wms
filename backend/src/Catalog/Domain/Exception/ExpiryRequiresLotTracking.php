<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A product is tracked by expiry date but not by lot.
 *
 * Protects CAT-04.
 */
final class ExpiryRequiresLotTracking extends DomainRuleViolation
{
    public static function create(): self
    {
        return new self(
            'catalog.expiry_requires_lot_tracking',
            [],
            'A product tracked by expiry date must also be tracked by lot.',
        );
    }
}
