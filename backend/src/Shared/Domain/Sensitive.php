<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Marks a field of a domain event that is personal data and must not be copied to the audit trail,
 * such as the email address of a user. The event still carries it for whoever needs to act on it.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER)]
final readonly class Sensitive
{
}
