<?php

declare(strict_types=1);

namespace App\Identity\Domain;

/**
 * The right of a signed-in user to ask for a new access token without the password (IDN-08).
 *
 * The client holds a secret and only its hash is kept. Every use exchanges the token for a
 * successor of the same family, so a secret works once. A family ends when its user signs out, when
 * a spent token shows up again (IDN-09), or when its session reaches its end (IDN-10).
 */
final class RefreshToken
{
    private ?\DateTimeImmutable $rotatedAt = null;

    private ?\DateTimeImmutable $revokedAt = null;

    private function __construct(
        private readonly string $id,
        private readonly string $familyId,
        private readonly UserId $userId,
        private readonly string $secretHash,
        private readonly \DateTimeImmutable $issuedAt,
        private readonly \DateTimeImmutable $expiresAt,
        private readonly \DateTimeImmutable $sessionEndsAt,
    ) {
    }

    /**
     * The first token of a session. The session ends after `$session`, however often it is renewed.
     */
    public static function start(string $id, UserId $userId, string $secret, \DateTimeImmutable $at, \DateInterval $lifetime, \DateInterval $session): self
    {
        $sessionEndsAt = $at->add($session);

        return new self($id, $id, $userId, self::hash($secret), $at, self::earliest($at->add($lifetime), $sessionEndsAt), $sessionEndsAt);
    }

    public static function hash(string $secret): string
    {
        return hash('sha256', $secret);
    }

    /**
     * Spends this token and returns the one that takes its place. Call it only when it is usable.
     * The new one never outlives the session (IDN-10).
     */
    public function rotate(string $id, string $secret, \DateTimeImmutable $at, \DateInterval $lifetime): self
    {
        $this->rotatedAt = $at;

        return new self($id, $this->familyId, $this->userId, self::hash($secret), $at, self::earliest($at->add($lifetime), $this->sessionEndsAt), $this->sessionEndsAt);
    }

    /**
     * @param \DateInterval $grace how long a spent token still counts as a second request of the same client
     */
    public function statusAt(\DateTimeImmutable $at, \DateInterval $grace): RefreshTokenStatus
    {
        if ($this->revokedAt instanceof \DateTimeImmutable) {
            return RefreshTokenStatus::Revoked;
        }

        if ($at >= $this->expiresAt) {
            return RefreshTokenStatus::Expired;
        }

        if (!$this->rotatedAt instanceof \DateTimeImmutable) {
            return RefreshTokenStatus::Usable;
        }

        return $at < $this->rotatedAt->add($grace) ? RefreshTokenStatus::JustRotated : RefreshTokenStatus::Reused;
    }

    public function revoke(\DateTimeImmutable $at): void
    {
        $this->revokedAt ??= $at;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt instanceof \DateTimeImmutable;
    }

    public function hasSecretHash(string $secretHash): bool
    {
        return hash_equals($this->secretHash, $secretHash);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function familyId(): string
    {
        return $this->familyId;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function issuedAt(): \DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    private static function earliest(\DateTimeImmutable $a, \DateTimeImmutable $b): \DateTimeImmutable
    {
        return $a < $b ? $a : $b;
    }
}
