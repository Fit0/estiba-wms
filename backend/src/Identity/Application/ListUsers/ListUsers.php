<?php

declare(strict_types=1);

namespace App\Identity\Application\ListUsers;

use App\Identity\Domain\User;
use App\Shared\Application\Bus\Query;
use App\Shared\Domain\Page;
use App\Shared\Domain\PageRequest;

/**
 * The users of the current tenant, by email address.
 *
 * @implements Query<Page<User>>
 */
final readonly class ListUsers implements Query
{
    public function __construct(public PageRequest $page)
    {
    }
}
