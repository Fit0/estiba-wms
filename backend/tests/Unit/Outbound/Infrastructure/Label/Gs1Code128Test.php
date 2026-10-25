<?php

declare(strict_types=1);

namespace App\Tests\Unit\Outbound\Infrastructure\Label;

use App\Outbound\Domain\Sscc;
use App\Outbound\Infrastructure\Label\Gs1Code128;
use PHPUnit\Framework\TestCase;

/**
 * The barcode on the label of a parcel.
 */
final class Gs1Code128Test extends TestCase
{
    private const string SSCC = '084000000000000017';

    public function testTheTableHasASymbolForEveryValueAndTheStop(): void
    {
        self::assertCount(107, Gs1Code128::patterns());
        self::assertCount(107, array_unique(Gs1Code128::patterns()), 'No two values share a pattern.');
    }

    public function testEverySymbolIsElevenModulesWideAndTheStopThirteen(): void
    {
        foreach (Gs1Code128::patterns() as $value => $pattern) {
            self::assertSame(106 === $value ? 13 : 11, array_sum(str_split($pattern)), \sprintf('Value %d.', $value));
        }
    }

    /**
     * A bar-code character has an even number of modules in its bars, which is how a scanner knows
     * it read one: a typo in the table breaks it.
     */
    public function testEverySymbolHasAnEvenNumberOfModulesInItsBars(): void
    {
        foreach (\array_slice(Gs1Code128::patterns(), 0, 106) as $value => $pattern) {
            $bars = (int) $pattern[0] + (int) $pattern[2] + (int) $pattern[4];

            self::assertSame(0, $bars % 2, \sprintf('Value %d.', $value));
        }
    }

    public function testSymbolsOpenWithStartCAndFnc1ThenTheIdentifierAndTheDigitsInPairs(): void
    {
        $symbols = Gs1Code128::symbolsFor(Sscc::fromString(self::SSCC));

        // Start C, FNC1, "00", "08", "40", "00" ×6, "17", the check symbol and the stop.
        self::assertSame([105, 102, 0, 8, 40, 0, 0, 0, 0, 0, 0, 17, 63, 106], $symbols);
    }

    public function testTheCheckSymbolIsTheWeightedSumModulo103(): void
    {
        $symbols = Gs1Code128::symbolsFor(Sscc::fromString(self::SSCC));

        $sum = 105;
        foreach (\array_slice($symbols, 1, -2) as $position => $value) {
            $sum += ($position + 1) * $value;
        }

        self::assertSame($sum % 103, $symbols[\count($symbols) - 2]);
    }

    public function testTheModulesAreStartCodeDataCheckAndStop(): void
    {
        $modules = Gs1Code128::modulesFor(Sscc::fromString(self::SSCC));

        self::assertSame(156, \strlen($modules), '13 symbols of 11 modules and a stop of 13.');
        self::assertSame('11010011100', substr($modules, 0, 11), 'Start C.');
        self::assertSame('1100011101011', substr($modules, -13), 'Stop.');
    }

    public function testReadingTheModulesBackGivesTheSameSymbols(): void
    {
        $sscc = Sscc::fromString(self::SSCC);
        $modules = Gs1Code128::modulesFor($sscc);

        $read = [];
        foreach (str_split(substr($modules, 0, -13), 11) as $symbol) {
            $read[] = $this->valueOf($symbol);
        }
        $read[] = $this->valueOf(substr($modules, -13));

        self::assertSame(Gs1Code128::symbolsFor($sscc), $read);
    }

    private function valueOf(string $modules): int
    {
        preg_match_all('/(.)\1*/', $modules, $runs);
        $pattern = implode('', array_map(strlen(...), $runs[0]));

        $value = array_search($pattern, Gs1Code128::patterns(), true);
        self::assertIsInt($value, 'Every symbol of the barcode is in the table.');

        return $value;
    }
}
