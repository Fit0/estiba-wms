<?php

declare(strict_types=1);

namespace App\Identity\Application\RevokeRefreshToken;

use App\Identity\Domain\RefreshToken;
use App\Identity\Domain\RefreshTokenRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Clock;

/**
 * The command bus owns the transaction and flushes.
 */
final readonly class RevokeRefreshTokenHandler implements CommandHandler
{
    public function __construct(
        private RefreshTokenRepository $tokens,
        private Clock $clock,
    ) {
    }

    public function __invoke(RevokeRefreshToken $command): null
    {
        $token = $this->tokens->findBySecretHash(RefreshToken::hash($command->secret));
        if (!$token instanceof RefreshToken) {
            return null;
        }

        $now = $this->clock->now();
        $token->revoke($now);
        foreach ($this->tokens->findLiveInFamily($token->familyId()) as $member) {
            $member->revoke($now);
        }

        return null;
    }
}
