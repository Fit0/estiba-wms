<?php

declare(strict_types=1);

namespace App\Identity\Application\DisableUser;

final readonly class DisableUser
{
    public function __construct(public string $email)
    {
    }
}
