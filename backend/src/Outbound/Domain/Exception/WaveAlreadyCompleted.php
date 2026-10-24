<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A wave whose pick lists are all done was released, cancelled or changed again.
 */
final class WaveAlreadyCompleted extends DomainRuleViolation
{
    public static function forId(string $id): self
    {
        return new self('outbound.wave_already_completed', ['id' => $id], \sprintf('Wave %s is already completed.', $id));
    }
}
