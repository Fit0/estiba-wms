<?php

declare(strict_types=1);

namespace App\Identity\Application\EnableUser;

use App\Identity\Domain\User;
use App\Shared\Application\Bus\Command;

/**
 * @implements Command<User>
 */
final readonly class EnableUser implements Command
{
    public function __construct(public string $email)
    {
    }
}
