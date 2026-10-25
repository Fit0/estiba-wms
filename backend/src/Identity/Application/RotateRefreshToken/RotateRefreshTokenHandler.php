<?php

declare(strict_types=1);

namespace App\Identity\Application\RotateRefreshToken;

use App\Identity\Application\IssuedRefreshToken;
use App\Identity\Application\RefreshSecretGenerator;
use App\Identity\Application\RefreshTokenPolicy;
use App\Identity\Domain\RefreshToken;
use App\Identity\Domain\RefreshTokenRepository;
use App\Identity\Domain\RefreshTokenStatus;
use App\Identity\Domain\User;
use App\Identity\Domain\UserRepository;
use App\Shared\Application\Bus\CommandHandler;
use App\Shared\Application\Clock;
use App\Shared\Application\IdGenerator;

/**
 * The command bus owns the transaction and flushes.
 */
final readonly class RotateRefreshTokenHandler implements CommandHandler
{
    public function __construct(
        private RefreshTokenRepository $tokens,
        private UserRepository $users,
        private RefreshSecretGenerator $secrets,
        private RefreshTokenPolicy $policy,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    public function __invoke(RotateRefreshToken $command): ?RotatedRefreshToken
    {
        $token = $this->tokens->findBySecretHash(RefreshToken::hash($command->secret));
        if (!$token instanceof RefreshToken) {
            return null;
        }

        $now = $this->clock->now();
        $status = $token->statusAt($now, $this->policy->grace());
        if (RefreshTokenStatus::Reused === $status) {
            // IDN-09: a spent token shows up again, so whoever holds the newer one may not be the owner.
            $this->revokeFamily($token, $now);
        }
        if (RefreshTokenStatus::Usable !== $status) {
            return null;
        }

        // IDN-06: a disabled user cannot renew a session either.
        $user = $this->users->find($token->userId());
        if (!$user instanceof User || !$user->isActive()) {
            $this->revokeFamily($token, $now);

            return null;
        }

        $secret = $this->secrets->next();
        $successor = $token->rotate($this->ids->next(), $secret, $now, $this->policy->lifetime());
        $this->tokens->save($successor);

        return new RotatedRefreshToken($user->email()->value, new IssuedRefreshToken($secret, $successor->expiresAt()));
    }

    private function revokeFamily(RefreshToken $token, \DateTimeImmutable $at): void
    {
        $token->revoke($at);
        foreach ($this->tokens->findLiveInFamily($token->familyId()) as $member) {
            $member->revoke($at);
        }
    }
}
