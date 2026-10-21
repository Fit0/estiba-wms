<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain;

use App\Shared\Domain\ProductFamily;
use App\Tests\Unit\Support\AssertsDomainErrors;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProductFamilyTest extends TestCase
{
    use AssertsDomainErrors;

    public function testIgnoresCaseAndSurroundingSpaces(): void
    {
        $family = ProductFamily::fromString(' camiseria ');

        self::assertSame('CAMISERIA', $family->value);
        self::assertTrue($family->equals(ProductFamily::fromString('CAMISERIA')));
        self::assertSame('PUNTO-FINO', ProductFamily::fromString('punto-fino')->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'a space inside' => ['PUNTO FINO'];
        yield 'a double hyphen' => ['PUNTO--FINO'];
        yield 'a trailing hyphen' => ['PUNTO-'];
        yield 'an accent' => ['CAMISERÍA'];
        yield 'too long' => [str_repeat('A', 21)];
    }

    #[DataProvider('invalidCodes')]
    public function testRejectsAnInvalidCode(string $value): void
    {
        self::assertDomainError('shared.product_family_invalid', ['value' => $value], static fn (): ProductFamily => ProductFamily::fromString($value));
    }
}
