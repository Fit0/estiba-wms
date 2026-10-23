<?php

declare(strict_types=1);

namespace App\Outbound\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * A wave was released into fewer than one pick list, or more than the limit.
 */
final class InvalidPickListCount extends DomainRuleViolation
{
    public const int MAX = 50;

    public static function forCount(int $count): self
    {
        return new self(
            'outbound.pick_list_count_invalid',
            ['count' => $count, 'max' => self::MAX],
            \sprintf('A wave is released into 1 to %d pick lists, and %d were asked for.', self::MAX, $count),
        );
    }
}
