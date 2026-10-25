<?php

declare(strict_types=1);

namespace App\Identity\Application\IssueRefreshToken;

use App\Identity\Application\IssuedRefreshToken;
use App\Identity\Application\RefreshSecretGenerator;
use App\Identity\Application\RefreshTokenPolicy;
use App\Identity\Domain\RefreshToken;
use App\Identity\Domain\RefreshTokenRepository;
use App\Identity\Domain\UserId;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Clock;
use App\Shared\Application\IdGenerator;

/**
 * The command bus owns the transaction and flushes.
 */
final readonly class IssueRefreshTokenHandler implements CommandHandler
{
    public function __construct(
        private RefreshTokenRepository $tokens,
        private RefreshSecretGenerator $secrets,
        private RefreshTokenPolicy $policy,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    public function __invoke(IssueRefreshToken $command): IssuedRefreshToken
    {
        $secret = $this->secrets->next();
        $token = RefreshToken::start(
            $this->ids->next(),
            UserId::fromString($command->userId),
            $secret,
            $this->clock->now(),
            $this->policy->lifetime(),
            $this->policy->session(),
        );
        $this->tokens->save($token);

        return new IssuedRefreshToken($secret, $token->expiresAt());
    }
}
