<?php

declare(strict_types=1);

namespace App\Identity\Application;

/**
 * How long a refresh token lives (ADR 0022). It comes from the configuration, so a deployment can
 * tighten it without a change of code.
 */
final readonly class RefreshTokenPolicy
{
    /**
     * @param positive-int $lifetimeSeconds how long a token works after it was issued
     * @param positive-int $sessionSeconds  how long a session lasts in all, however often it renews
     * @param positive-int $graceSeconds    how long a token that was just spent still counts as a second request of the same client
     */
    public function __construct(
        private int $lifetimeSeconds,
        private int $sessionSeconds,
        private int $graceSeconds,
    ) {
    }

    public function lifetime(): \DateInterval
    {
        return new \DateInterval('PT'.$this->lifetimeSeconds.'S');
    }

    public function session(): \DateInterval
    {
        return new \DateInterval('PT'.$this->sessionSeconds.'S');
    }

    public function grace(): \DateInterval
    {
        return new \DateInterval('PT'.$this->graceSeconds.'S');
    }
}
