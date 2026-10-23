<?php

declare(strict_types=1);

namespace App\Tests\Unit\Outbound\Domain;

use App\Outbound\Domain\CustomerName;
use App\Outbound\Domain\OrderReference;
use App\Outbound\Domain\ShippingAddress;
use App\Tests\Unit\Support\AssertsDomainErrors;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValueObjectsTest extends TestCase
{
    use AssertsDomainErrors;

    public function testKeepsTheReferenceAsGivenWithoutTheSurroundingSpaces(): void
    {
        self::assertSame('PED-2027/0153', OrderReference::fromString("  PED-2027/0153 \n")->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidReferences(): iterable
    {
        yield 'empty' => [''];
        yield 'only spaces' => ['   '];
        yield 'too long' => [str_repeat('P', 41)];
        yield 'control character' => ["PED\x07-1"];
    }

    #[DataProvider('invalidReferences')]
    public function testRejectsAnInvalidReference(string $value): void
    {
        self::assertDomainError('outbound.order_reference_invalid', ['maxLength' => 40], static fn (): OrderReference => OrderReference::fromString($value));
    }

    public function testAcceptsTheLongestReference(): void
    {
        self::assertSame(40, mb_strlen(OrderReference::fromString(str_repeat('P', 40))->value));
    }

    public function testComparesReferencesByValue(): void
    {
        self::assertTrue(OrderReference::fromString('A-1')->equals(OrderReference::fromString(' A-1 ')));
        self::assertFalse(OrderReference::fromString('A-1')->equals(OrderReference::fromString('A-2')));
    }

    public function testKeepsTheCustomerNameAsTyped(): void
    {
        self::assertSame('Franquicia Ñandú S.L.', CustomerName::fromString(' Franquicia Ñandú S.L. ')->value);
    }

    public function testRejectsAnInvalidCustomerName(): void
    {
        foreach (['', '  ', str_repeat('a', 101), "a\x07b"] as $value) {
            self::assertDomainError('outbound.customer_name_invalid', [], static fn (): CustomerName => CustomerName::fromString($value));
        }
        self::assertSame(100, mb_strlen(CustomerName::fromString(str_repeat('a', 100))->value));
    }

    public function testBuildsAnAddressWithTheCountryInCapitals(): void
    {
        $address = ShippingAddress::of(' Calle Mayor 12 ', '50001', 'Zaragoza', ' es ');

        self::assertSame('Calle Mayor 12', $address->street);
        self::assertSame('ES', $address->countryCode);
    }

    /**
     * @return iterable<string, array{string, string, string, string, string, int}>
     */
    public static function invalidAddresses(): iterable
    {
        yield 'no street' => ['street', '', '50001', 'Zaragoza', 'ES', 120];
        yield 'street too long' => ['street', str_repeat('a', 121), '50001', 'Zaragoza', 'ES', 120];
        yield 'no postal code' => ['postalCode', 'Calle', '', 'Zaragoza', 'ES', 12];
        yield 'postal code too long' => ['postalCode', 'Calle', str_repeat('1', 13), 'Zaragoza', 'ES', 12];
        yield 'no city' => ['city', 'Calle', '50001', '  ', 'ES', 60];
        yield 'control character in the city' => ['city', 'Calle', '50001', "Zara\x07goza", 'ES', 60];
        yield 'country of three letters' => ['countryCode', 'Calle', '50001', 'Zaragoza', 'ESP', 2];
        yield 'country with digits' => ['countryCode', 'Calle', '50001', 'Zaragoza', 'E1', 2];
        yield 'no country' => ['countryCode', 'Calle', '50001', 'Zaragoza', '', 2];
    }

    #[DataProvider('invalidAddresses')]
    public function testRejectsAnInvalidAddress(string $field, string $street, string $postalCode, string $city, string $country, int $maxLength): void
    {
        self::assertDomainError('outbound.shipping_address_invalid', ['field' => $field, 'maxLength' => $maxLength], static fn (): ShippingAddress => ShippingAddress::of($street, $postalCode, $city, $country));
    }
}
