<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain;

use App\Catalog\Domain\Gtin;
use App\Tests\Unit\Support\AssertsDomainErrors;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GtinTest extends TestCase
{
    use AssertsDomainErrors;

    #[DataProvider('validGtins')]
    public function testAcceptsAGtinWithACorrectCheckDigit(string $input, string $expectedNormalized): void
    {
        $gtin = Gtin::fromString($input);

        self::assertSame(trim($input), $gtin->value);
        self::assertSame($expectedNormalized, $gtin->normalized);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validGtins(): iterable
    {
        yield 'GTIN-8' => ['96385074', '00000096385074'];
        yield 'GTIN-12 (UPC)' => ['036000291452', '00036000291452'];
        yield 'GTIN-13 (EAN)' => ['4006381333931', '04006381333931'];
        yield 'GTIN-14' => ['10614141000415', '10614141000415'];
        yield 'spaces around are removed' => [' 4006381333931 ', '04006381333931'];
    }

    // CAT-02: the GS1 check digit.
    #[DataProvider('invalidGtins')]
    public function testRejectsAGtinThatBreaksTheFormatOrTheCheckDigit(string $input): void
    {
        self::assertDomainError(
            'catalog.gtin_invalid',
            ['value' => $input],
            static fn (): Gtin => Gtin::fromString($input),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidGtins(): iterable
    {
        yield 'wrong check digit' => ['4006381333932'];
        yield 'empty' => [''];
        yield 'too short' => ['1234567'];
        yield 'length 10' => ['4006381333'];
        yield 'too long' => ['400638133393100'];
        yield 'letters' => ['40063813339A1'];
        yield 'dashes' => ['4006-381-333-931'];
    }

    public function testComputesTheCheckDigit(): void
    {
        self::assertSame(1, Gtin::checkDigitFor('400638133393'));
        self::assertSame(4, Gtin::checkDigitFor('9638507'));
        self::assertSame(0, Gtin::checkDigitFor('0'));
    }

    public function testTreatsAUpcAndTheEanWithALeadingZeroAsTheSameGtin(): void
    {
        $upc = Gtin::fromString(ProductMother::UPC_12);
        $ean = Gtin::fromString('0036000291452');

        self::assertTrue($upc->equals($ean));
        self::assertFalse($upc->equals(Gtin::fromString(ProductMother::EAN_13)));
    }
}
