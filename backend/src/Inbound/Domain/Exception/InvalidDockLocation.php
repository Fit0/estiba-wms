<?php

declare(strict_types=1);

namespace App\Inbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A receipt was opened at a location that cannot take it: the tenant has no such location, it is
 * not a dock, or it is blocked.
 */
final class InvalidDockLocation extends DomainRuleViolation
{
    public static function unknown(string $locationId): self
    {
        return self::because($locationId, 'unknown');
    }

    public static function notADock(string $locationId): self
    {
        return self::because($locationId, 'not_dock');
    }

    public static function blocked(string $locationId): self
    {
        return self::because($locationId, 'blocked');
    }

    private static function because(string $locationId, string $reason): self
    {
        return new self(
            'inbound.dock_location_invalid',
            ['locationId' => $locationId, 'reason' => $reason],
            \sprintf('Location %s cannot take a receipt (%s).', $locationId, $reason),
        );
    }
}
