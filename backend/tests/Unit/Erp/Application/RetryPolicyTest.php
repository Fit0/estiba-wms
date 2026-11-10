<?php

declare(strict_types=1);

namespace App\Tests\Unit\Erp\Application;

use App\Erp\Application\RetryPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RetryPolicyTest extends TestCase
{
    /**
     * @return iterable<string, array{int, string}>
     */
    public static function waits(): iterable
    {
        yield 'the first failure waits a minute' => [1, '2026-11-09 10:01:00'];
        yield 'the second two' => [2, '2026-11-09 10:02:00'];
        yield 'the third four' => [3, '2026-11-09 10:04:00'];
        yield 'the sixth thirty-two' => [6, '2026-11-09 10:32:00'];
        yield 'the seventh would be sixty-four, and an hour is the most' => [7, '2026-11-09 11:00:00'];
    }

    #[DataProvider('waits')]
    public function testWaitsLongerEachTimeUpToAnHour(int $attempts, string $expected): void
    {
        $now = new \DateTimeImmutable('2026-11-09 10:00:00', new \DateTimeZone('UTC'));

        self::assertSame($expected, new RetryPolicy()->nextAttempt($attempts, $now)->format('Y-m-d H:i:s'));
    }

    public function testGivesUpAtTheEighthFailure(): void
    {
        $policy = new RetryPolicy();

        self::assertTrue($policy->canTryAfter(RetryPolicy::MAX_ATTEMPTS - 1));
        self::assertFalse($policy->canTryAfter(RetryPolicy::MAX_ATTEMPTS));
    }
}
