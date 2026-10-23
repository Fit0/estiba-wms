<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A pick list has no lines, or a line that asks for no units.
 */
final class InvalidPickList extends DomainRuleViolation
{
    public static function noLines(): self
    {
        return new self('outbound.pick_list_invalid', ['reason' => 'empty'], 'A pick list needs at least one line.');
    }

    public static function noUnits(int $position): self
    {
        return new self('outbound.pick_list_invalid', ['reason' => 'no_units'], \sprintf('Line %d of the pick list asks for no units.', $position));
    }
}
