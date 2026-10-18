<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Catalog\Domain\PackagingType;
use App\Shared\Domain\DomainRuleViolation;

/**
 * A packaging level holds fewer than one base unit.
 *
 * Protects CAT-03.
 */
final class InvalidPackagingLevel extends DomainRuleViolation
{
    public static function forBaseUnits(PackagingType $type, int $baseUnits): self
    {
        return new self(
            'catalog.packaging_level_invalid',
            ['type' => $type->value, 'baseUnits' => $baseUnits],
            \sprintf('A %s must hold a positive whole number of base units, got %d.', $type->value, $baseUnits),
        );
    }
}
