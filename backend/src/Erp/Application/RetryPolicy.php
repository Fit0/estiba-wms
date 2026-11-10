<?php

declare(strict_types=1);

namespace App\Erp\Application;

/**
 * When to try again to tell the ERP, and when to give up (ADR 0049): a minute after the first failure, then double
 * each time up to an hour, and the eighth failure is the last. An ERP that is down for a night has the confirmations
 * of the day waiting, and they go out when it is back.
 */
final readonly class RetryPolicy
{
    public const int MAX_ATTEMPTS = 8;

    private const int FIRST_DELAY_SECONDS = 60;

    private const int MAX_DELAY_SECONDS = 3600;

    /**
     * @param int $attempts how many times it has failed, from 1
     */
    public function canTryAfter(int $attempts): bool
    {
        return $attempts < self::MAX_ATTEMPTS;
    }

    /**
     * @param int $attempts how many times it has failed, from 1
     */
    public function nextAttempt(int $attempts, \DateTimeImmutable $now): \DateTimeImmutable
    {
        $seconds = min(self::FIRST_DELAY_SECONDS * (2 ** max(0, $attempts - 1)), self::MAX_DELAY_SECONDS);

        return $now->modify(\sprintf('+%d seconds', $seconds));
    }
}
