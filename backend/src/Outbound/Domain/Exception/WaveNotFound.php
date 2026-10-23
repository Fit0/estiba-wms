<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * No wave with this identifier exists for the tenant.
 */
final class WaveNotFound extends DomainRuleViolation
{
    public static function forId(string $id): self
    {
        return new self('outbound.wave_not_found', ['id' => $id], \sprintf('No wave with id %s.', $id));
    }
}
