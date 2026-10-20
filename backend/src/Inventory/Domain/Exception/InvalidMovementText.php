<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Shared\Domain\DomainRuleViolation;

/**
 * The reference or the reason of a movement is empty, too long or has control characters.
 *
 * Protects STK-09 for the reason of an adjustment.
 */
final class InvalidMovementText extends DomainRuleViolation
{
    public static function forField(string $field, int $maxLength): self
    {
        return new self(
            'inventory.movement_text_invalid',
            ['field' => $field, 'maxLength' => $maxLength],
            \sprintf('The %s of a movement must have 1 to %d characters and no control characters.', $field, $maxLength),
        );
    }
}
