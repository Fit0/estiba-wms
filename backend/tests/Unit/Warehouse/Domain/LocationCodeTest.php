<?php

declare(strict_types=1);

namespace App\Tests\Unit\Warehouse\Domain;

use App\Tests\Unit\Support\AssertsDomainErrors;
use App\Warehouse\Domain\LocationCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LocationCodeTest extends TestCase
{
    use AssertsDomainErrors;

    public function testSplitsAisleModuleAndLevel(): void
    {
        $code = LocationCode::fromString('A-03-02');

        self::assertSame('A-03-02', $code->value);
        self::assertSame('A', $code->aisle);
        self::assertSame('03', $code->module);
        self::assertSame('02', $code->level);
    }

    public function testNormalizesCaseAndSpaces(): void
    {
        $code = LocationCode::fromString(' ab-123-10 ');

        self::assertSame('AB-123-10', $code->value);
        self::assertSame('AB', $code->aisle);
        self::assertSame('123', $code->module);
    }

    // WH-03: a location code is written aisle-module-level.
    #[DataProvider('invalidCodes')]
    public function testRejectsACodeThatIsNotAisleModuleLevel(string $input): void
    {
        self::assertDomainError(
            'warehouse.location_code_invalid',
            ['value' => $input],
            static fn (): LocationCode => LocationCode::fromString($input),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'no separators' => ['A0302'];
        yield 'module too short' => ['A-3-02'];
        yield 'module too long' => ['A-0003-02'];
        yield 'level too short' => ['A-03-2'];
        yield 'aisle too long' => ['ABC-03-02'];
        yield 'aisle is a digit' => ['1-03-02'];
        yield 'level is not a number' => ['A-03-0X'];
        yield 'extra part' => ['A-03-02-01'];
        yield 'spaces inside' => ['A - 03 - 02'];
    }

    public function testComparesByValue(): void
    {
        self::assertTrue(LocationCode::fromString('a-03-02')->equals(LocationCode::fromString('A-03-02')));
        self::assertFalse(LocationCode::fromString('A-03-02')->equals(LocationCode::fromString('A-03-03')));
    }
}
