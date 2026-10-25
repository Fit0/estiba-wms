<?php

declare(strict_types=1);

namespace App\Tests\Unit\Outbound\Domain;

use App\Outbound\Domain\CarrierName;
use App\Outbound\Domain\TrackingNumber;
use App\Tests\Unit\Support\AssertsDomainErrors;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Who takes the parcels, and the number to follow them.
 */
final class DispatchValueObjectsTest extends TestCase
{
    use AssertsDomainErrors;

    public function testACarrierIsKeptAsTypedWithoutTheSpacesAround(): void
    {
        self::assertSame('Correos Express', CarrierName::fromString('  Correos Express ')->value);
        self::assertSame('SEUR', (string) CarrierName::fromString('SEUR'));
    }

    public function testATrackingNumberIsKeptAsTypedWithoutTheSpacesAround(): void
    {
        self::assertSame('SE0042261187', TrackingNumber::fromString(' SE0042261187 ')->value);
        self::assertSame('SE0042261187', (string) TrackingNumber::fromString('SE0042261187'));
    }

    #[DataProvider('invalidTexts')]
    public function testACarrierHasOneToSixtyCharactersAndNoControlOnes(string $value): void
    {
        self::assertDomainError('outbound.carrier_name_invalid', [], static fn (): CarrierName => CarrierName::fromString($value));
    }

    #[DataProvider('invalidTexts')]
    public function testATrackingNumberHasOneToSixtyCharactersAndNoControlOnes(string $value): void
    {
        self::assertDomainError('outbound.tracking_number_invalid', [], static fn (): TrackingNumber => TrackingNumber::fromString($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTexts(): iterable
    {
        yield 'empty' => [''];
        yield 'only spaces' => ['   '];
        yield 'too long' => [str_repeat('a', 61)];
        yield 'a control character' => ["SE\x07123"];
    }

    public function testSixtyCharactersAreEnough(): void
    {
        self::assertSame(60, mb_strlen(CarrierName::fromString(str_repeat('ñ', 60))->value));
    }
}
