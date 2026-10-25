<?php

declare(strict_types=1);

namespace App\Identity\Application\IssueRefreshToken;

use App\Identity\Application\IssuedRefreshToken;
use App\Shared\Application\Bus\Command;

/**
 * Starts a session for a user who has just proved who they are.
 *
 * @implements Command<IssuedRefreshToken>
 */
final readonly class IssueRefreshToken implements Command
{
    public function __construct(public string $userId)
    {
    }
}
