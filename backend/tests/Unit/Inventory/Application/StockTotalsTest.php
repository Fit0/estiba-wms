<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inventory\Application;

use App\Inventory\Application\StockOverview\StockTotals;
use PHPUnit\Framework\TestCase;

final class StockTotalsTest extends TestCase
{
    public function testAddsUpEveryStatus(): void
    {
        $zaragoza = new StockTotals(10, 8, 2, 0, 0);
        $sevilla = new StockTotals(7, 4, 0, 1, 2);

        self::assertEquals(new StockTotals(17, 12, 2, 1, 2), $zaragoza->plus($sevilla));
    }

    public function testNoneIsNeutral(): void
    {
        $totals = new StockTotals(5, 5, 0, 0, 0);

        self::assertEquals($totals, StockTotals::none()->plus($totals));
    }
}
