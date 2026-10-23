<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A wave that is already on the floor was released again, or its orders were changed.
 *
 * Protects OUT-07.
 */
final class WaveAlreadyReleased extends DomainRuleViolation
{
    public static function forId(string $id): self
    {
        return new self('outbound.wave_already_released', ['id' => $id], \sprintf('Wave %s is already released.', $id));
    }
}
