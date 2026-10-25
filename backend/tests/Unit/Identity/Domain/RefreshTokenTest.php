<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Domain;

use App\Identity\Domain\RefreshToken;
use App\Identity\Domain\RefreshTokenStatus;
use App\Tests\Unit\Support\Ids;
use PHPUnit\Framework\TestCase;

/**
 * IDN-08 to IDN-10: a refresh token works once, and a session has an end.
 */
final class RefreshTokenTest extends TestCase
{
    private const string LIFETIME = 'P7D';

    private const string SESSION = 'P30D';

    private const string GRACE = 'PT10S';

    public function testANewTokenCanBeUsedUntilItExpires(): void
    {
        $token = $this->started();

        self::assertSame(RefreshTokenStatus::Usable, $token->statusAt($this->at('+6 days'), $this->grace()));
        self::assertSame(RefreshTokenStatus::Expired, $token->statusAt($this->at('+7 days'), $this->grace()));
    }

    public function testOnlyTheHashOfTheSecretIsKept(): void
    {
        $token = $this->started('the-secret');

        self::assertTrue($token->hasSecretHash(RefreshToken::hash('the-secret')));
        self::assertFalse($token->hasSecretHash('the-secret'));
        self::assertFalse($token->hasSecretHash(RefreshToken::hash('another-secret')));
    }

    public function testExchangingATokenGivesASuccessorOfTheSameFamilyAndSpendsTheOldOne(): void
    {
        $token = $this->started('first');
        $later = $this->at('+1 day');

        $successor = $token->rotate(Ids::next(), 'second', $later, new \DateInterval(self::LIFETIME));

        self::assertSame($token->familyId(), $successor->familyId());
        self::assertTrue($successor->hasSecretHash(RefreshToken::hash('second')));
        self::assertSame(RefreshTokenStatus::Usable, $successor->statusAt($later, $this->grace()));
        self::assertSame(RefreshTokenStatus::Reused, $token->statusAt($this->at('+1 day +11 seconds'), $this->grace()));
    }

    // Two tabs open at once ask with the same secret: the second one is not an attack.
    public function testASpentTokenIsOnlyTheSameClientAskingAgainDuringTheGrace(): void
    {
        $token = $this->started();
        $token->rotate(Ids::next(), 'second', $this->at('+1 day'), new \DateInterval(self::LIFETIME));

        self::assertSame(RefreshTokenStatus::JustRotated, $token->statusAt($this->at('+1 day +9 seconds'), $this->grace()));
        self::assertSame(RefreshTokenStatus::Reused, $token->statusAt($this->at('+1 day +10 seconds'), $this->grace()));
    }

    public function testARevokedTokenIsNeverUsableAgain(): void
    {
        $token = $this->started();

        $token->revoke($this->at('+1 hour'));
        $token->revoke($this->at('+2 hours'));

        self::assertTrue($token->isRevoked());
        self::assertSame(RefreshTokenStatus::Revoked, $token->statusAt($this->at('+3 hours'), $this->grace()));
    }

    // IDN-10: renewing again and again never makes a session last longer than it was allowed.
    public function testASessionEndsEvenIfItIsRenewedEveryDay(): void
    {
        $token = $this->started();

        $current = $token;
        for ($day = 1; $day <= 29; ++$day) {
            $current = $current->rotate(Ids::next(), 'secret-'.$day, $this->at('+'.$day.' days'), new \DateInterval(self::LIFETIME));
        }
        self::assertSame(RefreshTokenStatus::Usable, $current->statusAt($this->at('+29 days'), $this->grace()));

        $last = $current->rotate(Ids::next(), 'last', $this->at('+29 days'), new \DateInterval(self::LIFETIME));

        self::assertEquals($this->at('+30 days'), $last->expiresAt(), 'The last token stops where the session stops, not a week later.');
        self::assertSame(RefreshTokenStatus::Expired, $last->statusAt($this->at('+30 days'), $this->grace()));
    }

    private function started(string $secret = 'secret'): RefreshToken
    {
        return RefreshToken::start(Ids::next(), IdentityMother::userId(), $secret, $this->at(), new \DateInterval(self::LIFETIME), new \DateInterval(self::SESSION));
    }

    private function at(string $modifier = 'now'): \DateTimeImmutable
    {
        return IdentityMother::at()->modify($modifier);
    }

    private function grace(): \DateInterval
    {
        return new \DateInterval(self::GRACE);
    }
}
