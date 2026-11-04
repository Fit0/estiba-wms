<?php

declare(strict_types=1);

namespace App\Tests\Unit\Analytics\Domain;

use App\Analytics\Domain\KpiPeriod;
use App\Tests\Unit\Support\AssertsDomainErrors;
use PHPUnit\Framework\TestCase;

final class KpiPeriodTest extends TestCase
{
    use AssertsDomainErrors;

    public function testAPeriodGoesFromAMomentUntilALaterOne(): void
    {
        $period = KpiPeriod::between(new \DateTimeImmutable('2026-10-01T00:00:00+00:00'), new \DateTimeImmutable('2026-10-02T12:00:00+00:00'));

        self::assertSame(36.0, $period->hours());
    }

    public function testItMustEndAfterItStarts(): void
    {
        $moment = new \DateTimeImmutable('2026-10-01T00:00:00+00:00');

        self::assertDomainError('analytics.period_invalid', ['reason' => 'not_forward', 'maxDays' => 366], static fn (): KpiPeriod => KpiPeriod::between($moment, $moment));
        self::assertDomainError('analytics.period_invalid', ['reason' => 'not_forward', 'maxDays' => 366], static fn (): KpiPeriod => KpiPeriod::between($moment, $moment->modify('-1 day')));
    }

    public function testItLastsAYearAtMost(): void
    {
        $from = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');

        self::assertSame(366 * 24.0, KpiPeriod::between($from, $from->modify('+366 days'))->hours());
        self::assertDomainError('analytics.period_invalid', ['reason' => 'too_long', 'maxDays' => 366], static fn (): KpiPeriod => KpiPeriod::between($from, $from->modify('+367 days')));
    }

    public function testTheLastDaysEndNow(): void
    {
        $now = new \DateTimeImmutable('2026-11-03T10:00:00+00:00');

        $period = KpiPeriod::lastDays(7, $now);

        self::assertEquals(new \DateTimeImmutable('2026-10-27T10:00:00+00:00'), $period->from);
        self::assertSame($now, $period->until);
    }
}
