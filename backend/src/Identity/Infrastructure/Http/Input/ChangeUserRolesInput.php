<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Http\Input;

final readonly class ChangeUserRolesInput
{
    /**
     * @param list<string> $roles `admin`, `supervisor` or `operator`
     */
    public function __construct(public array $roles)
    {
    }
}
