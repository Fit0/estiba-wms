<?php

declare(strict_types=1);

namespace App\Tests\Unit\Outbound\Domain;

use App\Outbound\Domain\Sscc;
use App\Tests\Unit\Support\AssertsDomainErrors;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The code that identifies a parcel (OUT-19).
 */
final class SsccTest extends TestCase
{
    use AssertsDomainErrors;

    public function testAcceptsAnSsccWhoseCheckDigitIsRight(): void
    {
        self::assertSame('084000000000000017', Sscc::fromString('084000000000000017')->value);
        self::assertSame('084000000000000017', Sscc::fromString(' 084000000000000017 ')->value);
    }

    #[DataProvider('invalidValues')]
    public function testRejectsAnythingThatIsNotAnSsccWithItsCheckDigit(string $value): void
    {
        self::assertDomainError('outbound.sscc_invalid', ['value' => $value], static fn (): Sscc => Sscc::fromString($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'wrong check digit' => ['084000000000000018'];
        yield 'too short' => ['08400000000000001'];
        yield 'too long' => ['0840000000000000170'];
        yield 'not digits' => ['08400000000000001X'];
        yield 'empty' => [''];
    }

    public function testComposesTheSsccOfASerialOfTheCompany(): void
    {
        self::assertSame('084000000000000017', Sscc::compose(0, '8400000', 1)->value);
        self::assertSame('384000000000000018', Sscc::compose(3, '8400000', 1)->value);
    }

    public function testPadsTheSerialWithZerosToFillTheCodeAndEndsWithItsCheckDigit(): void
    {
        $sscc = Sscc::compose(0, '8400000', 123456789);

        self::assertSame(18, \strlen($sscc->value));
        self::assertSame('0840000012345678', substr($sscc->value, 0, 16));
        self::assertSame($sscc->value, Sscc::fromString($sscc->value)->value, 'What is composed passes the check digit.');
    }

    public function testALongerPrefixLeavesRoomForFewerSerials(): void
    {
        self::assertSame(18, \strlen(Sscc::compose(0, '8400000123', 999999)->value));
        self::assertDomainError('outbound.sscc_serials_exhausted', ['prefix' => '8400000123'], static fn (): Sscc => Sscc::compose(0, '8400000123', 10_000_000));
    }

    public function testTheLastSerialOfASevenDigitPrefixIsNineNines(): void
    {
        self::assertSame(18, \strlen(Sscc::compose(0, '8400000', 999_999_999)->value));
        self::assertDomainError('outbound.sscc_serials_exhausted', ['prefix' => '8400000'], static fn (): Sscc => Sscc::compose(0, '8400000', 1_000_000_000));
    }

    #[DataProvider('invalidParts')]
    public function testRejectsPartsThatCannotMakeAnSscc(int $extension, string $prefix, int $serial): void
    {
        self::assertDomainError('outbound.sscc_invalid', ['value' => \sprintf('%d%s/%d', $extension, $prefix, $serial)], static fn (): Sscc => Sscc::compose($extension, $prefix, $serial));
    }

    /**
     * @return iterable<string, array{int, string, int}>
     */
    public static function invalidParts(): iterable
    {
        yield 'extension above 9' => [10, '8400000', 1];
        yield 'negative extension' => [-1, '8400000', 1];
        yield 'prefix with letters' => [0, '84A0000', 1];
        yield 'prefix too short' => [0, '84000', 1];
        yield 'prefix too long' => [0, '84000001234', 1];
        yield 'negative serial' => [0, '8400000', -1];
    }

    public function testTwoSsccsAreEqualWhenTheirDigitsAre(): void
    {
        self::assertTrue(Sscc::compose(0, '8400000', 1)->equals(Sscc::fromString('084000000000000017')));
        self::assertFalse(Sscc::compose(0, '8400000', 1)->equals(Sscc::compose(0, '8400000', 2)));
        self::assertSame('084000000000000017', (string) Sscc::compose(0, '8400000', 1));
    }
}
