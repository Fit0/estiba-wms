<?php

declare(strict_types=1);

namespace App\Identity\Application\GetUser;

use App\Identity\Domain\User;
use App\Shared\Application\Bus\Query;

/**
 * One user of the current tenant.
 *
 * @implements Query<User>
 */
final readonly class GetUser implements Query
{
    public function __construct(public string $userId)
    {
    }
}
