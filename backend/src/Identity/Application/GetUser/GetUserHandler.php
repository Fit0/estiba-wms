<?php

declare(strict_types=1);

namespace App\Identity\Application\GetUser;

use App\Identity\Application\TenantUsers;
use App\Identity\Domain\Exception\UserNotFound;
use App\Identity\Domain\User;
use App\Shared\Application\Bus\QueryHandler;

final readonly class GetUserHandler implements QueryHandler
{
    public function __construct(private TenantUsers $users)
    {
    }

    /**
     * @throws UserNotFound
     */
    public function __invoke(GetUser $query): User
    {
        return $this->users->get($query->userId);
    }
}
