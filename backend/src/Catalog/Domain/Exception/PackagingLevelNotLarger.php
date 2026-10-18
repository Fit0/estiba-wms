<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Catalog\Domain\PackagingLevel;
use App\Shared\Domain\DomainRuleViolation;

/**
 * A packaging level does not hold more base units than the level before it
 * (or than the base unit itself, which is 1).
 *
 * Protects CAT-03.
 */
final class PackagingLevelNotLarger extends DomainRuleViolation
{
    public static function than(PackagingLevel $level, int $previousBaseUnits): self
    {
        return new self(
            'catalog.packaging_level_not_larger',
            ['type' => $level->type->value, 'baseUnits' => $level->baseUnits, 'previousBaseUnits' => $previousBaseUnits],
            \sprintf('A %s of %d base units must be larger than the previous level of %d.', $level->type->value, $level->baseUnits, $previousBaseUnits),
        );
    }
}
