<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Identity\Domain\Email;
use App\Shared\Domain\DomainRuleViolation;

/**
 * A user that is already disabled was disabled again.
 */
final class UserAlreadyDisabled extends DomainRuleViolation
{
    public static function forEmail(Email $email): self
    {
        return new self(
            'identity.user_already_disabled',
            ['email' => $email->value],
            \sprintf('User %s is already disabled.', $email->value),
        );
    }
}
