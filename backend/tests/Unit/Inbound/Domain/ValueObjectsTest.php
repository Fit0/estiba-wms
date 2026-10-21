<?php

declare(strict_types=1);

namespace App\Tests\Unit\Inbound\Domain;

use App\Inbound\Domain\AsnReference;
use App\Inbound\Domain\AsnStatus;
use App\Inbound\Domain\LotNumber;
use App\Inbound\Domain\SupplierName;
use App\Tests\Unit\Support\AssertsDomainErrors;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValueObjectsTest extends TestCase
{
    use AssertsDomainErrors;

    public function testKeepsTextsAsGivenWithoutSurroundingSpaces(): void
    {
        self::assertSame('ALB-2027/0153', AsnReference::fromString('  ALB-2027/0153 ')->value);
        self::assertSame('Textiles del Sur, S.L.', SupplierName::fromString(' Textiles del Sur, S.L. ')->value);
        self::assertSame('L-0425', LotNumber::fromString(' L-0425 ')->value);
        self::assertTrue(AsnReference::fromString('A-1')->equals(AsnReference::fromString(' A-1')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTexts(): iterable
    {
        yield 'empty' => [''];
        yield 'only spaces' => ['   '];
        yield 'a control character' => ["ALB\t01"];
    }

    #[DataProvider('invalidTexts')]
    public function testRejectsAnInvalidReference(string $value): void
    {
        self::assertDomainError('inbound.asn_reference_invalid', ['maxLength' => 40], static fn (): AsnReference => AsnReference::fromString($value));
    }

    #[DataProvider('invalidTexts')]
    public function testRejectsAnInvalidSupplier(string $value): void
    {
        self::assertDomainError('inbound.supplier_name_invalid', ['maxLength' => 100], static fn (): SupplierName => SupplierName::fromString($value));
    }

    #[DataProvider('invalidTexts')]
    public function testRejectsAnInvalidLot(string $value): void
    {
        self::assertDomainError('inbound.lot_number_invalid', ['value' => $value], static fn (): LotNumber => LotNumber::fromString($value));
    }

    public function testRejectsTextsThatAreTooLong(): void
    {
        self::assertSame(40, mb_strlen(AsnReference::fromString(str_repeat('ñ', 40))->value));
        self::assertDomainError('inbound.asn_reference_invalid', ['maxLength' => 40], static fn (): AsnReference => AsnReference::fromString(str_repeat('A', 41)));
        self::assertDomainError('inbound.supplier_name_invalid', ['maxLength' => 100], static fn (): SupplierName => SupplierName::fromString(str_repeat('A', 101)));
        self::assertDomainError('inbound.lot_number_invalid', ['value' => str_repeat('A', 41)], static fn (): LotNumber => LotNumber::fromString(str_repeat('A', 41)));
    }

    // INB-04
    public function testOnlyClosedAndCancelledAsnsRefuseReceipts(): void
    {
        $accepting = array_filter(AsnStatus::cases(), static fn (AsnStatus $status): bool => $status->acceptsReceipts());

        self::assertSame([AsnStatus::Expected, AsnStatus::PartiallyReceived, AsnStatus::Received], array_values($accepting));
    }
}
