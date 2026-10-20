<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Identity\Domain\Email;
use App\Shared\Domain\DomainRuleViolation;

/**
 * A user that is not disabled was enabled.
 */
final class UserNotDisabled extends DomainRuleViolation
{
    public static function forEmail(Email $email): self
    {
        return new self(
            'identity.user_not_disabled',
            ['email' => $email->value],
            \sprintf('User %s is not disabled.', $email->value),
        );
    }
}
