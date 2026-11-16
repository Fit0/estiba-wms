<?php

declare(strict_types=1);

namespace App\Identity\Application\ListUsers;

use App\Identity\Domain\User;
use App\Identity\Domain\UserRepository;
use App\Shared\Application\Bus\QueryHandler;
use App\Shared\Application\CurrentTenant;
use App\Shared\Domain\Page;

final readonly class ListUsersHandler implements QueryHandler
{
    public function __construct(
        private UserRepository $users,
        private CurrentTenant $tenant,
    ) {
    }

    /**
     * @return Page<User>
     */
    public function __invoke(ListUsers $query): Page
    {
        return $this->users->search($this->tenant->id(), $query->page);
    }
}
