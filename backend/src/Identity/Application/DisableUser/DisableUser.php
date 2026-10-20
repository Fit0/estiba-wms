<?php

declare(strict_types=1);

namespace App\Identity\Application\DisableUser;

use App\Identity\Domain\User;
use App\Shared\Application\Bus\Command;

/**
 * @implements Command<User>
 */
final readonly class DisableUser implements Command
{
    public function __construct(public string $email)
    {
    }
}
