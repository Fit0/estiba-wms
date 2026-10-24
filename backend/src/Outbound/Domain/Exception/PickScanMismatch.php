<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * What the operator scanned is not what the line asks for: another location, another product or another lot.
 *
 * Protects OUT-11.
 */
final class PickScanMismatch extends DomainRuleViolation
{
    public const string LOCATION = 'location';

    public const string PRODUCT = 'product';

    public const string LOT = 'lot';

    public static function of(string $what, int $position): self
    {
        return new self(
            'outbound.pick_scan_mismatch',
            ['what' => $what, 'position' => $position],
            \sprintf('The %s scanned does not match line %d of the pick list.', $what, $position),
        );
    }
}
