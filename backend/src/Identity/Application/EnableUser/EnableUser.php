<?php

declare(strict_types=1);

namespace App\Identity\Application\EnableUser;

final readonly class EnableUser
{
    public function __construct(public string $email)
    {
    }
}
