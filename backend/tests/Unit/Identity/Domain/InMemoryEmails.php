<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\Email;
use App\Identity\Domain\EmailRegistry;

/**
 * Stands in for the database in unit tests: it remembers which email addresses are taken.
 */
final class InMemoryEmails implements EmailRegistry
{
    /** @var array<string, true> */
    private array $taken = [];

    public function take(Email $email): self
    {
        $this->taken[$email->value] = true;

        return $this;
    }

    public function isTaken(Email $email): bool
    {
        return isset($this->taken[$email->value]);
    }
}
