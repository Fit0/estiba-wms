<?php

declare(strict_types=1);

namespace App\Tests\Unit\Warehouse\Domain;

use App\Tests\Unit\Support\AssertsDomainErrors;
use App\Warehouse\Domain\WarehouseCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WarehouseCodeTest extends TestCase
{
    use AssertsDomainErrors;

    #[DataProvider('validCodes')]
    public function testAcceptsAndNormalizesAValidCode(string $input, string $expected): void
    {
        self::assertSame($expected, WarehouseCode::fromString($input)->value);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validCodes(): iterable
    {
        yield 'upper case' => ['MAD1', 'MAD1'];
        yield 'lower case becomes upper case' => ['mad1', 'MAD1'];
        yield 'spaces around are removed' => [' BCN-NORTE ', 'BCN-NORTE'];
        yield 'shortest' => ['M1', 'M1'];
        yield 'longest' => ['ABCDEFGHIJKL', 'ABCDEFGHIJKL'];
    }

    // WH-01: the format a warehouse code must follow.
    #[DataProvider('invalidCodes')]
    public function testRejectsACodeThatBreaksTheFormat(string $input): void
    {
        self::assertDomainError(
            'warehouse.warehouse_code_invalid',
            ['value' => $input],
            static fn (): WarehouseCode => WarehouseCode::fromString($input),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'too short' => ['M'];
        yield 'too long' => ['ABCDEFGHIJKLM'];
        yield 'inner space' => ['MAD 1'];
        yield 'leading hyphen' => ['-MAD'];
        yield 'trailing hyphen' => ['MAD-'];
        yield 'double hyphen' => ['MAD--1'];
        yield 'accent' => ['MÁD1'];
        yield 'symbol' => ['MAD_1'];
    }

    public function testComparesByValue(): void
    {
        self::assertTrue(WarehouseCode::fromString('mad1')->equals(WarehouseCode::fromString('MAD1')));
        self::assertFalse(WarehouseCode::fromString('MAD1')->equals(WarehouseCode::fromString('MAD2')));
    }
}
