<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A cancelled wave was released or cancelled again. A cancelled wave is finished.
 */
final class WaveAlreadyCancelled extends DomainRuleViolation
{
    public static function forId(string $id): self
    {
        return new self('outbound.wave_already_cancelled', ['id' => $id], \sprintf('Wave %s is already cancelled.', $id));
    }
}
