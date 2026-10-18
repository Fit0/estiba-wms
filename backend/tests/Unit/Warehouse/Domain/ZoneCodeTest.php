<?php

declare(strict_types=1);

namespace App\Tests\Unit\Warehouse\Domain;

use App\Tests\Unit\Support\AssertsDomainErrors;
use App\Warehouse\Domain\ZoneCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ZoneCodeTest extends TestCase
{
    use AssertsDomainErrors;

    #[DataProvider('validCodes')]
    public function testAcceptsAndNormalizesAValidCode(string $input, string $expected): void
    {
        self::assertSame($expected, ZoneCode::fromString($input)->value);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validCodes(): iterable
    {
        yield 'upper case' => ['FAST', 'FAST'];
        yield 'lower case becomes upper case' => ['chill', 'CHILL'];
        yield 'single character' => ['a', 'A'];
        yield 'longest' => [' ABCDE12345 ', 'ABCDE12345'];
    }

    // WH-02: the format a zone code must follow.
    #[DataProvider('invalidCodes')]
    public function testRejectsACodeThatBreaksTheFormat(string $input): void
    {
        self::assertDomainError(
            'warehouse.zone_code_invalid',
            ['value' => $input],
            static fn (): ZoneCode => ZoneCode::fromString($input),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => ['ABCDE123456'];
        yield 'inner space' => ['FA ST'];
        yield 'hyphen' => ['FAST-1'];
    }
}
