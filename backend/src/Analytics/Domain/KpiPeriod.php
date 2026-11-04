<?php

declare(strict_types=1);

namespace App\Analytics\Domain;

use App\Analytics\Domain\Exception\InvalidKpiPeriod;

/**
 * The period a figure is about: from a moment, included, until a later one, not included. It is at
 * least a second long and at most a year, so a query stays cheap.
 */
final readonly class KpiPeriod
{
    public const int MAX_DAYS = 366;

    private function __construct(
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $until,
    ) {
    }

    /**
     * @throws InvalidKpiPeriod when it does not end after it starts or it is longer than a year
     */
    public static function between(\DateTimeImmutable $from, \DateTimeImmutable $until): self
    {
        if ($until <= $from) {
            throw InvalidKpiPeriod::notForward();
        }

        if ($until > $from->modify(\sprintf('+%d days', self::MAX_DAYS))) {
            throw InvalidKpiPeriod::tooLong();
        }

        return new self($from, $until);
    }

    /**
     * The days before a moment, which is the default view of a dashboard.
     */
    public static function lastDays(int $days, \DateTimeImmutable $now): self
    {
        return self::between($now->modify(\sprintf('-%d days', $days)), $now);
    }

    public function hours(): float
    {
        return ($this->until->getTimestamp() - $this->from->getTimestamp()) / 3600;
    }
}
