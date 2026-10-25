<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application;

use App\Identity\Application\IssueRefreshToken\IssueRefreshToken;
use App\Identity\Application\IssueRefreshToken\IssueRefreshTokenHandler;
use App\Identity\Application\RefreshSecretGenerator;
use App\Identity\Application\RefreshTokenPolicy;
use App\Identity\Application\RevokeRefreshToken\RevokeRefreshToken;
use App\Identity\Application\RevokeRefreshToken\RevokeRefreshTokenHandler;
use App\Identity\Application\RotateRefreshToken\RotateRefreshToken;
use App\Identity\Application\RotateRefreshToken\RotateRefreshTokenHandler;
use App\Identity\Domain\User;
use App\Tests\Unit\Identity\Domain\IdentityMother;
use PHPUnit\Framework\TestCase;

/**
 * Issuing, renewing and revoking a session (IDN-06 and IDN-08 to IDN-10).
 */
final class RefreshTokenUseCasesTest extends TestCase
{
    private IdentityFakes $fakes;

    private MovableClock $clock;

    private RefreshSecretGenerator $secrets;

    private User $user;

    #[\Override]
    protected function setUp(): void
    {
        $this->fakes = new IdentityFakes();
        $this->clock = new MovableClock(IdentityMother::at());
        $this->secrets = IdentityFakes::secrets();
        $this->user = IdentityMother::user('ana@example.com');
        $this->fakes->users->save($this->user);
    }

    public function testIssuesASecretThatLivesForTheLifetimeOfAToken(): void
    {
        $issued = $this->issue()(new IssueRefreshToken($this->user->id()->value));

        self::assertSame('secret-1', $issued->secret);
        self::assertEquals(IdentityMother::at()->modify('+7 days'), $issued->expiresAt);
        self::assertCount(1, $this->fakes->refreshTokens->saved);
    }

    public function testExchangesTheSecretForANewOneForTheSameUser(): void
    {
        $first = $this->issue()(new IssueRefreshToken($this->user->id()->value));

        $rotated = $this->rotate()(new RotateRefreshToken($first->secret));

        self::assertNotNull($rotated);
        self::assertSame('ana@example.com', $rotated->email);
        self::assertNotSame($first->secret, $rotated->refreshToken->secret);
        self::assertCount(2, $this->fakes->refreshTokens->saved);
    }

    public function testASecretThatIsNotKnownGetsNothing(): void
    {
        self::assertNull($this->rotate()(new RotateRefreshToken('made-up')));
        self::assertSame([], $this->fakes->refreshTokens->saved);
    }

    public function testAnExpiredSecretGetsNothing(): void
    {
        $first = $this->issue()(new IssueRefreshToken($this->user->id()->value));
        $this->clock->now = $this->clock->now->modify('+7 days');

        self::assertNull($this->rotate()(new RotateRefreshToken($first->secret)));
    }

    public function testASecretAskedTwiceAtOnceIsRefusedWithoutEndingTheSession(): void
    {
        $first = $this->issue()(new IssueRefreshToken($this->user->id()->value));
        $second = $this->rotate()(new RotateRefreshToken($first->secret));
        self::assertNotNull($second);
        $this->clock->now = $this->clock->now->modify('+2 seconds');

        self::assertNull($this->rotate()(new RotateRefreshToken($first->secret)), 'The copy is refused.');

        self::assertNotNull($this->rotate()(new RotateRefreshToken($second->refreshToken->secret)), 'The newer token still works.');
    }

    // IDN-09
    public function testASpentSecretThatShowsUpLaterEndsTheWholeSession(): void
    {
        $first = $this->issue()(new IssueRefreshToken($this->user->id()->value));
        $second = $this->rotate()(new RotateRefreshToken($first->secret));
        self::assertNotNull($second);
        $this->clock->now = $this->clock->now->modify('+1 minute');

        self::assertNull($this->rotate()(new RotateRefreshToken($first->secret)), 'Someone kept a copy.');

        self::assertNull($this->rotate()(new RotateRefreshToken($second->refreshToken->secret)), 'The newer token is revoked too.');
    }

    // IDN-06
    public function testADisabledUserCannotRenewTheSession(): void
    {
        $first = $this->issue()(new IssueRefreshToken($this->user->id()->value));
        $this->user->disable($this->clock->now);

        self::assertNull($this->rotate()(new RotateRefreshToken($first->secret)));
    }

    public function testSigningOutEndsTheSessionAndEveryTokenRenewedFromIt(): void
    {
        $first = $this->issue()(new IssueRefreshToken($this->user->id()->value));
        $second = $this->rotate()(new RotateRefreshToken($first->secret));
        self::assertNotNull($second);

        $this->revoke()(new RevokeRefreshToken($second->refreshToken->secret));

        self::assertNull($this->rotate()(new RotateRefreshToken($second->refreshToken->secret)));
    }

    public function testSigningOutWithASecretThatIsNotKnownIsNotAnError(): void
    {
        $this->revoke()(new RevokeRefreshToken('made-up'));

        self::assertSame([], $this->fakes->refreshTokens->saved);
    }

    private function issue(): IssueRefreshTokenHandler
    {
        return new IssueRefreshTokenHandler($this->fakes->refreshTokens, $this->secrets, $this->policy(), IdentityFakes::ids(), $this->clock);
    }

    private function rotate(): RotateRefreshTokenHandler
    {
        return new RotateRefreshTokenHandler($this->fakes->refreshTokens, $this->fakes->users, $this->secrets, $this->policy(), IdentityFakes::ids(), $this->clock);
    }

    private function revoke(): RevokeRefreshTokenHandler
    {
        return new RevokeRefreshTokenHandler($this->fakes->refreshTokens, $this->clock);
    }

    private function policy(): RefreshTokenPolicy
    {
        return new RefreshTokenPolicy(7 * 86400, 30 * 86400, 10);
    }
}
