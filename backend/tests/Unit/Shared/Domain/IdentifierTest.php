<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain;

use App\Shared\Domain\TenantId;
use App\Tests\Unit\Support\AssertsDomainErrors;
use App\Warehouse\Domain\WarehouseId;
use App\Warehouse\Domain\ZoneId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IdentifierTest extends TestCase
{
    use AssertsDomainErrors;

    public function testKeepsAValidUuid(): void
    {
        $id = WarehouseId::fromString('01929b3e-7c1a-7000-8000-000000000001');

        self::assertSame('01929b3e-7c1a-7000-8000-000000000001', $id->value);
        self::assertSame('01929b3e-7c1a-7000-8000-000000000001', (string) $id);
    }

    public function testNormalizesCaseAndSurroundingSpaces(): void
    {
        $id = WarehouseId::fromString('  01929B3E-7C1A-7000-8000-00000000000A ');

        self::assertSame('01929b3e-7c1a-7000-8000-00000000000a', $id->value);
    }

    #[DataProvider('invalidValues')]
    public function testRejectsAnythingThatIsNotAUuid(string $value): void
    {
        self::assertDomainError(
            'shared.identifier_invalid',
            ['type' => 'WarehouseId', 'value' => $value],
            static fn (): WarehouseId => WarehouseId::fromString($value),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'empty' => [''];
        yield 'plain text' => ['not-a-uuid'];
        yield 'missing dashes' => ['01929b3e7c1a70008000000000000001'];
        yield 'version 0' => ['01929b3e-7c1a-0000-8000-000000000001'];
        yield 'bad variant' => ['01929b3e-7c1a-7000-c000-000000000001'];
        yield 'not hexadecimal' => ['zzzzzzzz-7c1a-7000-8000-000000000001'];
    }

    public function testEqualsOnlyAnIdentifierOfTheSameTypeAndValue(): void
    {
        $id = WarehouseId::fromString('01929b3e-7c1a-7000-8000-000000000001');

        self::assertTrue($id->equals(WarehouseId::fromString('01929b3e-7c1a-7000-8000-000000000001')));
        self::assertFalse($id->equals(WarehouseId::fromString('01929b3e-7c1a-7000-8000-000000000002')));
        self::assertFalse($id->equals(ZoneId::fromString('01929b3e-7c1a-7000-8000-000000000001')));
        self::assertFalse($id->equals(TenantId::fromString('01929b3e-7c1a-7000-8000-000000000001')));
    }
}
